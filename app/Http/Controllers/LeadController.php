<?php

namespace App\Http\Controllers;

use App\Events\CommissionStatusChanged;
use App\Events\DealCreated as DealCreatedEvent;
use App\Mail\ResellerInvitation;
use App\Models\DealPartner;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\LeadNote;
use App\Models\CommissionSplit;
use App\Models\Reseller;
use App\Services\NotificationDispatchService;
use App\Services\ReferrerInvitationDeduplicationService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $relations = ['commissionSplits', 'notes', 'history', 'attachments', 'links'];

        // Include partner associations when explicitly requested (Referrer portal deal list).
        // Eager-loads DealPartner + Partner user in a single query per lead batch (no N+1).
        $includePartners = $request->boolean('include_partners');
        if ($includePartners) {
            $relations[] = 'dealPartners.partner';
        }

        $query = Lead::with($relations)->orderBy('created_at', 'desc');

        // Derive tenant from authenticated context; fall back to query param
        $tenantId = TenantContext::id() ?? $request->query('tenant_id');
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif (!TenantContext::isSuperAdmin()) {
            abort(403, 'Tenant context required.');
        }

        if ($request->filled('reseller_name')) {
            $query->where('reseller_name', $request->reseller_name);
        }

        // Paginate to prevent OOM on large tenants; callers may request all via per_page=all
        $perPage = $request->get('per_page', 50);
        if ($perPage === 'all' && TenantContext::isSuperAdmin()) {
            $leads = $query->get();
        } else {
            $perPage = min(200, max(1, (int) $perPage));
            $paginated = $query->paginate($perPage);
            $leads     = $paginated->getCollection();
        }

        if ($includePartners) {
            // Map partner data into a clean `partners` array on each lead.
            // Only active DealPartner records are included.
            $leads = $leads->map(function (Lead $lead) {
                $data = $lead->toArray();
                $data['partners'] = $lead->dealPartners
                    ->where('status', 'active')
                    ->map(fn(DealPartner $dp) => [
                        'id'             => $dp->partner_user_id,
                        'display_name'   => $dp->partner?->display_name ?? null,
                        'email'          => $dp->partner?->email ?? null,
                        'setup_complete' => $dp->partner?->isSetupComplete() ?? false,
                    ])
                    ->values()
                    ->toArray();
                return $data;
            });
        }

        if (isset($paginated)) {
            return response()->json([
                'data'      => $leads->values(),
                'total'     => $paginated->total(),
                'page'      => $paginated->currentPage(),
                'per_page'  => $paginated->perPage(),
                'last_page' => $paginated->lastPage(),
            ]);
        }

        return response()->json($leads);
    }

    public function store(Request $request): JsonResponse
    {
        try {
        return $this->doStore($request);
        } catch (\Throwable $e) {
            \Log::error('LeadController::store failed: ' . $e->getMessage(), [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
            return response()->json(['message' => $e->getMessage(), 'error_detail' => $e->getFile().':'.$e->getLine()], 500);
        }
    }

    private function doStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'               => 'required|string',
            'stage'              => 'required|string',
            'status'             => 'nullable|in:active,expiring,expired,reassigned,declined',
            'days_left'          => 'nullable|integer',
            'reseller_name'      => 'required|string',
            'new_reseller_email' => 'nullable|email',
            'organization_id'    => 'nullable|uuid',
            'commission_status'  => 'nullable|in:pending,locked,paid',
            'base_cost'          => 'nullable|numeric|min:0',
            'added_amount'       => 'nullable|numeric|min:0',
            'deal_value'         => 'nullable|numeric',
            'data'               => 'nullable|array',
            'commission_splits'  => 'nullable|array',
        ]);

        // Derive tenant from authenticated context; fall back to request body
        // (consistent with ResellerController pattern for session-authenticated tenant admins)
        $tenantId = TenantContext::id() ?? $request->input('tenant_id');
        if (!$tenantId) {
            return response()->json(['message' => 'No tenant context established.'], 403);
        }

        // ── LGU IDS: one active deal per organization ─────────────────
        // LOCKED RULE — do not remove or generalise (LGU IDS pipeline protection)
        if (!empty($data['organization_id']) && $tenantId === 'lgu-ids') {
            $existing = DB::table('leads')
                ->where('tenant_id', 'lgu-ids')
                ->where('organization_id', $data['organization_id'])
                ->whereNotIn('status', ['expired', 'declined'])
                ->select('reseller_name')
                ->first();

            if ($existing) {
                return response()->json([
                    'message'    => 'This municipality already has an active deal.',
                    'claimed_by' => $existing->reseller_name,
                    'error_code' => 'ORG_ALREADY_CLAIMED',
                ], 422);
            }
        }

        // ── LGU IDS: set days_left from pipeline stage rules ──────────
        // LOCKED RULE — do not generalise (LGU IDS pipeline protection)
        $daysLeft = $data['days_left'] ?? 21;
        if ($tenantId === 'lgu-ids') {
            $stageRule = DB::table('tenant_pipeline_stage_rules')
                ->where('tenant_id', 'lgu-ids')
                ->where('stage', $data['stage'])
                ->first();
            if ($stageRule) {
                $daysLeft = $stageRule->max_days;
            }
        }

        // Resellers and Partners cannot set financial fields — always compute server-side
        $isReferrer = \Illuminate\Support\Facades\Auth::guard('reseller')->check();
        $isPartner  = \Illuminate\Support\Facades\Auth::guard('partner')->check();

        $dealValue   = (float) ($data['deal_value'] ?? 0);
        $baseCost    = (float) ($data['base_cost']   ?? 0);
        $addedAmount = (float) ($data['added_amount'] ?? 0);

        // For LGU IDS referrers: always compute base_cost from range formula (LOCKED)
        if (($isReferrer || $isPartner) && $tenantId === 'lgu-ids' && $dealValue > 0) {
            $baseCost    = \App\Services\LguIds\LguIdsPricingService::lookupBaseCost($dealValue);
            $addedAmount = $dealValue - $baseCost;
        } elseif (!$isReferrer && !$isPartner) {
            // Admins/managers: use supplied values
            $baseCost    = (float) ($data['base_cost']    ?? 0);
            $addedAmount = (float) ($data['added_amount'] ?? 0);
            $dealValue   = $baseCost + $addedAmount ?: $dealValue;
        }

        // ── LGU IDS: default editable deal value ₱4,000,000 ─────────────
        // ADDITIVE RULE — does not change base_cost/added_amount computation.
        // Only applied when deal_value is still 0 after the base+added calculation.
        if ($tenantId === 'lgu-ids' && $dealValue == 0) {
            $dealValue = 4_000_000.00;
        }

        $lead = Lead::create([
            'tenant_id'         => $tenantId,
            'name'              => $data['name'],
            'stage'             => $data['stage'],
            'status'            => $data['status'] ?? 'active',
            'days_left'         => $daysLeft,
            'organization_id'   => $data['organization_id'] ?? null,
            'reseller_name'     => $data['reseller_name'],
            'commission_status' => $data['commission_status'] ?? 'pending',
            'base_cost'         => $baseCost,
            'added_amount'      => $addedAmount,
            'deal_value'        => $dealValue,
            'data'              => $data['data'] ?? [],
        ]);

        if (!empty($data['commission_splits'])) {
            foreach ($data['commission_splits'] as $split) {
                CommissionSplit::create([
                    'lead_id'         => $lead->id,
                    'reseller_name'   => $split['reseller_name'],
                    'percentage'      => $split['percentage'],
                    'role'            => $split['role'],
                    'activity_status' => $split['activity_status'] ?? 'active',
                ]);
            }
        }

        [$actorIdForCreate, $actorRoleForCreate, $actorNameForCreate] = $this->resolveActor();
        LeadHistory::create([
            'lead_id'    => $lead->id,
            'tenant_id'  => $lead->tenant_id,
            'action'     => 'Deal created',
            'type'       => 'assignment',
            'category'   => 'deal',
            'actor_name' => $actorNameForCreate,
            'actor_role' => $actorRoleForCreate,
            'date'       => now()->toDateString(),
        ]);

        // Fire event → triggers deal created emails (reseller + tenant admin)
        // Wrapped in try-catch — listener failure must not cause a 500 on deal creation
        try {
            DealCreatedEvent::dispatch(
                leadId:        $lead->id,
                leadName:      $lead->name,
                tenantId:      $lead->tenant_id,
                resellerName:  $lead->reseller_name,
                stage:         $lead->stage,
                dealValue:     (float) ($lead->deal_value ?? 0),
                daysLeft:      $lead->days_left ?? 21,
            );
        } catch (\Throwable $e) {
            Log::error('DealCreatedEvent dispatch failed (non-fatal): ' . $e->getMessage(), [
                'lead_id'   => $lead->id,
                'tenant_id' => $lead->tenant_id,
            ]);
        }

        // Handle Referrer assignment/invitation with full deduplication
        $resellerCreated = false;
        $inviteSent      = false;
        $inviteAction    = null;

        if (!empty($data['new_reseller_email'])) {
            $tenantName = DB::table('tenants')->where('id', $tenantId)->value('name') ?? 'Referral Bunny';

            [$actorId, $actorRole] = $this->resolveActor();

            $result = app(ReferrerInvitationDeduplicationService::class)->handleReferrerAssignment(
                tenantId:   $tenantId,
                email:      strtolower(trim($data['new_reseller_email'])),
                name:       $data['reseller_name'],
                dealId:     $lead->id,
                source:     'deal_creation',
                tenantName: $tenantName,
                actorId:    $actorId,
                actorRole:  $actorRole,
            );

            $resellerCreated = ($result['action'] === 'created');
            $inviteSent      = $result['email_sent'];
            $inviteAction    = $result['action'];
        }

        return response()->json(array_merge(
            $lead->load(['commissionSplits', 'notes', 'history'])->toArray(),
            [
                'reseller_created' => $resellerCreated,
                'invite_sent'      => $inviteSent,
                'invite_action'    => $inviteAction,
            ]
        ), 201);
    } // end doStore

    private function resolveActor(): array
    {
        if (\Illuminate\Support\Facades\Auth::guard('tenant')->check()) {
            $u = \Illuminate\Support\Facades\Auth::guard('tenant')->user();
            return [$u->id ?? 'unknown', 'Tenant Admin', $u->name ?? $u->email ?? 'Admin'];
        }
        if (\Illuminate\Support\Facades\Auth::guard('web')->check()) {
            $u = \Illuminate\Support\Facades\Auth::guard('web')->user();
            return [$u->id ?? 'unknown', 'Super Admin', $u->name ?? $u->email ?? 'Super Admin'];
        }
        if (\Illuminate\Support\Facades\Auth::guard('reseller')->check()) {
            $u = \Illuminate\Support\Facades\Auth::guard('reseller')->user();
            return [$u->id ?? 'unknown', 'Referrer', $u->name ?? 'Referrer'];
        }
        return ['system', 'System', 'System'];
    }

    public function show(Lead $lead): JsonResponse
    {
        $lead->assertBelongsToCurrentTenant();
        return response()->json($lead->load(['commissionSplits', 'notes', 'history', 'attachments', 'links']));
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $lead->assertBelongsToCurrentTenant();

        // Resellers and Partners cannot modify financial fields — strip them from the request
        $isReferrer = \Illuminate\Support\Facades\Auth::guard('reseller')->check();
        $isPartner  = \Illuminate\Support\Facades\Auth::guard('partner')->check();
        if ($isReferrer || $isPartner) {
            $request->request->remove('base_cost');
            $request->request->remove('added_amount');
            $request->request->remove('deal_value');
        }

        $data = $request->validate([
            'name'              => 'sometimes|string',
            'stage'             => 'sometimes|string',
            'status'            => 'sometimes|in:active,expiring,expired,reassigned,declined',
            'days_left'         => 'sometimes|integer',
            'reseller_name'     => 'sometimes|string',
            'commission_status' => 'sometimes|in:pending,locked,paid',
            'base_cost'         => 'sometimes|numeric|min:0',
            'added_amount'      => 'sometimes|numeric|min:0',
            'deal_value'        => 'sometimes|numeric',
            'data'              => 'sometimes|array',
        ]);

        // Capture old financial values before update for history log
        $oldDealValue   = (float) ($lead->deal_value   ?? 0);
        $oldBaseCost    = (float) ($lead->base_cost     ?? 0);
        $oldAddedAmount = (float) ($lead->added_amount  ?? 0);

        // Auto-recompute deal_value whenever financial fields change
        if (isset($data['base_cost']) || isset($data['added_amount'])) {
            $newBc = (float)($data['base_cost']    ?? $lead->base_cost);
            $newAa = (float)($data['added_amount'] ?? $lead->added_amount);
            $data['deal_value'] = $newBc + $newAa;
        }

        // Backend formula guard: if all three values are supplied, verify consistency
        if (isset($data['deal_value'], $data['base_cost'], $data['added_amount'])) {
            $calc = app(\App\Services\CommissionCalculationService::class);
            if (!$calc->formulaIsValid(
                (float) $data['deal_value'],
                (float) $data['base_cost'],
                (float) $data['added_amount']
            )) {
                return response()->json([
                    'message' => 'Financial formula mismatch: Base Cost + Added Amount must equal Deal Value.',
                    'expected_deal_value' => round((float)$data['base_cost'] + (float)$data['added_amount'], 2),
                ], 422);
            }
        }

        // ── LGU IDS: reset days_left when stage advances ──────────────
        // LOCKED RULE — do not generalise (LGU IDS pipeline protection)
        if (isset($data['stage']) && $data['stage'] !== $lead->stage && $lead->tenant_id === 'lgu-ids') {
            $stageRule = DB::table('tenant_pipeline_stage_rules')
                ->where('tenant_id', 'lgu-ids')
                ->where('stage', $data['stage'])
                ->first();
            if ($stageRule) {
                $data['days_left'] = $stageRule->max_days;
                $data['status']    = 'active';
            }
        }

        // Fire commission status event if changed
        if (isset($data['commission_status']) && $data['commission_status'] !== $lead->commission_status) {
            CommissionStatusChanged::dispatch(
                leadId:        $lead->id,
                leadName:      $lead->name,
                tenantId:      $lead->tenant_id,
                resellerName:  $lead->reseller_name,
                newStatus:     $data['commission_status'],
                dealValue:     (float) ($lead->deal_value ?? 0),
            );
        }

        $lead->update($data);

        // ── Audit: log financial changes to deal history ──────────────
        $newDealValue   = (float) ($lead->deal_value   ?? 0);
        $newBaseCost    = (float) ($lead->base_cost     ?? 0);
        $newAddedAmount = (float) ($lead->added_amount  ?? 0);

        $financialChanged = abs($newDealValue - $oldDealValue)     > 0.01
                         || abs($newBaseCost - $oldBaseCost)       > 0.01
                         || abs($newAddedAmount - $oldAddedAmount) > 0.01;

        if ($financialChanged) {
            [$actorId, $actorRole] = $this->resolveActor();
            app(\App\Services\DealActivityService::class)->financialBreakdownChanged(
                $lead,
                ['deal_value' => $oldDealValue,   'base_cost' => $oldBaseCost,   'added_amount' => $oldAddedAmount],
                ['deal_value' => $newDealValue,    'base_cost' => $newBaseCost,   'added_amount' => $newAddedAmount],
                $actorRole,
            );
        }

        return response()->json($lead->fresh(['commissionSplits', 'notes', 'history']));
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $lead->assertBelongsToCurrentTenant();
        $lead->delete();
        return response()->json(['message' => 'Lead deleted.']);
    }

    public function moveStage(Request $request, Lead $lead): JsonResponse
    {
        // Partners are read-only — they cannot advance pipeline stages
        if (Auth::guard('partner')->check()) {
            return response()->json(['error' => 'Partners cannot modify deal stages.'], 403);
        }

        $lead->assertBelongsToCurrentTenant();

        $stages       = ['introduction', 'presentation', 'contract_sent', 'signed', 'paid'];
        $currentIndex = array_search($lead->stage, $stages);

        // Accept explicit target stage OR advance to next in sequence
        if ($request->filled('stage') && in_array($request->stage, $stages)) {
            $targetStage = $request->stage;
        } else {
            if ($currentIndex === false || $currentIndex >= count($stages) - 1) {
                return response()->json(['message' => 'Already at final stage.'], 422);
            }
            $targetStage = $stages[$currentIndex + 1];
        }

        if ($targetStage === $lead->stage) {
            return response()->json(['message' => 'Deal is already at this stage.'], 422);
        }

        $isLocking    = $targetStage === 'signed';
        $isPaid       = $targetStage === 'paid';
        $note         = trim((string) $request->input('note', ''));
        $capturedStage = $lead->stage;

        $updates = [
            'stage'             => $targetStage,
            'commission_status' => $isLocking ? 'locked' : ($isPaid ? 'paid' : $lead->commission_status),
        ];

        // ── LGU IDS: reset days_left when stage advances ──────────────
        // LOCKED RULE — mirrors update() (LGU IDS pipeline protection)
        if ($lead->tenant_id === 'lgu-ids') {
            $stageRule = DB::table('tenant_pipeline_stage_rules')
                ->where('tenant_id', 'lgu-ids')
                ->where('stage', $targetStage)
                ->first();
            if ($stageRule) {
                $updates['days_left'] = $stageRule->max_days;
                $updates['status']    = 'active';
            }
        }

        [$actorIdStage, $actorRoleStage, $actorNameStage] = $this->resolveActor();

        DB::transaction(function () use ($lead, $updates, $targetStage, $capturedStage, $isLocking, $isPaid, $note, $actorNameStage, $actorRoleStage) {
            $lead->update($updates);
            $commPool = app(\App\Services\CommissionCalculationService::class)
                ->commissionPool((float) $lead->added_amount);

            $activity = app(\App\Services\DealActivityService::class);

            // Stage moved
            $activity->record($lead,
                'Stage moved: ' . ucwords(str_replace('_', ' ', $capturedStage))
                    . ' \u{2192} ' . ucwords(str_replace('_', ' ', $targetStage))
                    . ($note !== '' ? ' \u{2014} ' . $note : ''),
                'stage',
                [
                    'category'   => 'stage',
                    'actor_name' => $actorNameStage,
                    'actor_role' => $actorRoleStage,
                    'old_values' => ['stage' => $capturedStage],
                    'new_values' => ['stage' => $targetStage, 'note' => $note ?: null],
                ]
            );

            // Commission locked (Signed)
            if ($isLocking) {
                $activity->record($lead,
                    'Commission locked at \u{20b1}' . number_format($commPool, 2) . ' (deal moved to Signed)',
                    'commission',
                    [
                        'category'   => 'commission',
                        'actor_name' => $actorNameStage,
                        'actor_role' => $actorRoleStage,
                        'new_values' => ['commission_pool' => $commPool, 'status' => 'locked'],
                    ]
                );
            }

            // Commission paid (Paid)
            if ($isPaid) {
                $activity->record($lead,
                    'Commission marked as paid \u{2014} pool \u{20b1}' . number_format($commPool, 2),
                    'commission',
                    [
                        'category'   => 'commission',
                        'actor_name' => $actorNameStage,
                        'actor_role' => $actorRoleStage,
                        'new_values' => ['commission_pool' => $commPool, 'status' => 'paid'],
                    ]
                );
            }
        });

        // Notify tenant admins and assigned referrer about stage movement
        try {
            $stageName  = ucwords(str_replace('_', ' ', $targetStage));
            $fromName   = ucwords(str_replace('_', ' ', $capturedStage));
            $priority   = ($isPaid || $isLocking) ? 'high' : 'normal';
            $minute     = now()->format('YmdHi');

            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $lead->tenant_id,
                category:     'deal_pipeline',
                priority:     $priority,
                title:        "Deal moved to {$stageName}",
                body:         "\"{$lead->name}\" was moved from {$fromName} to {$stageName}.",
                actionUrl:    "/tenant/{$lead->tenant_id}/deals/{$lead->id}",
                actionLabel:  'View Deal',
                dedupeSuffix: "{$lead->id}:stage:{$targetStage}:{$minute}",
            );

            if ($lead->reseller_name) {
                $reseller = Reseller::where('tenant_id', $lead->tenant_id)
                    ->where('name', $lead->reseller_name)
                    ->first();
                if ($reseller) {
                    app(NotificationDispatchService::class)->dispatchToReseller(
                        resellerId:   (string) $reseller->id,
                        tenantId:     $lead->tenant_id,
                        category:     'deal_pipeline',
                        priority:     $priority,
                        title:        "Your deal moved to {$stageName}",
                        body:         "\"{$lead->name}\" has been moved to {$stageName}.",
                        actionUrl:    "/reseller/{$lead->tenant_id}/deals/{$lead->id}",
                        actionLabel:  'View Deal',
                        dedupeSuffix: "{$lead->id}:stage:{$targetStage}:r:{$minute}",
                    );
                }
            }
        } catch (\Throwable) {}

        return response()->json($lead->fresh(['commissionSplits', 'history']));
    }

    public function addNote(Request $request, Lead $lead): JsonResponse
    {
        // Partners are read-only; use DealCommentController for note access
        if (Auth::guard('partner')->check()) {
            return response()->json(['error' => 'Partners cannot add notes via this endpoint.'], 403);
        }

        $data = $request->validate([
            'text'   => 'required|string',
            'author' => 'required|string',
        ]);

        $note = LeadNote::create(['lead_id' => $lead->id, ...$data]);
        return response()->json($note, 201);
    }

    public function reassign(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'reseller_name' => 'required|string',
            'reset_stage'   => 'boolean',
        ]);

        $oldReferrerName = $lead->reseller_name ?? '';

        $lead->update([
            'reseller_name'     => $data['reseller_name'],
            'stage'             => ($data['reset_stage'] ?? true) ? 'introduction' : $lead->stage,
            'days_left'         => 21,
            'status'            => 'active',
            'commission_status' => 'pending',
        ]);

        CommissionSplit::where('lead_id', $lead->id)->delete();
        CommissionSplit::create([
            'lead_id'         => $lead->id,
            'reseller_name'   => $data['reseller_name'],
            'percentage'      => 100,
            'role'            => 'primary',
            'activity_status' => 'active',
        ]);

        [$actorIdRa, $actorRoleRa, $actorNameRa] = $this->resolveActor();
        app(\App\Services\DealActivityService::class)->record($lead,
            'Referrer reassigned: ' . ($oldReferrerName ?: 'None')
                . ' \u{2192} ' . $data['reseller_name'],
            'assignment',
            [
                'category'   => 'assignment',
                'actor_name' => $actorNameRa,
                'actor_role' => $actorRoleRa,
                'reseller'   => $data['reseller_name'],
                'old_values' => ['referrer_name' => $oldReferrerName],
                'new_values' => ['referrer_name' => $data['reseller_name']],
            ]
        );

        // Notify tenant admins and new referrer about reassignment
        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $lead->tenant_id,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Deal reassigned',
                body:         "\"{$lead->name}\" has been reassigned to {$data['reseller_name']}.",
                actionUrl:    "/tenant/{$lead->tenant_id}/deals/{$lead->id}",
                actionLabel:  'View Deal',
                dedupeSuffix: "{$lead->id}:reassign:" . now()->format('YmdHi'),
            );

            $newReseller = Reseller::where('tenant_id', $lead->tenant_id)
                ->where('name', $data['reseller_name'])
                ->first();
            if ($newReseller) {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   (string) $newReseller->id,
                    tenantId:     $lead->tenant_id,
                    category:     'deal_pipeline',
                    priority:     'high',
                    title:        'New deal assigned to you',
                    body:         "You have been assigned to \"{$lead->name}\".",
                    actionUrl:    "/reseller/{$lead->tenant_id}/deals/{$lead->id}",
                    actionLabel:  'View Deal',
                    dedupeSuffix: "{$lead->id}:reassign:r:" . now()->format('YmdHi'),
                );
            }
        } catch (\Throwable) {}

        return response()->json($lead->fresh(['commissionSplits', 'history']));
    }

    public function updateCommissionSplits(Request $request, Lead $lead): JsonResponse
    {
        // Partners are read-only — they cannot modify commission splits
        if (Auth::guard('partner')->check()) {
            return response()->json(['error' => 'Partners cannot modify commission splits.'], 403);
        }

        $data = $request->validate([
            'splits'            => 'required|array',
            'splits.*.reseller_name'   => 'required|string',
            'splits.*.percentage'      => 'required|numeric',
            'splits.*.role'            => 'required|in:primary,secondary,tertiary',
            'splits.*.activity_status' => 'nullable|string',
        ]);

        CommissionSplit::where('lead_id', $lead->id)->delete();
        foreach ($data['splits'] as $split) {
            CommissionSplit::create(['lead_id' => $lead->id, ...$split]);
        }

        LeadHistory::create([
            'lead_id' => $lead->id,
            'action'  => 'Commission split updated',
            'type'    => 'commission',
            'date'    => now()->toDateString(),
        ]);

        return response()->json($lead->fresh(['commissionSplits']));
    }
}
