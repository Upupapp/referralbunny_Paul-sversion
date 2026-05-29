<?php

namespace App\Http\Controllers;

use App\Events\CommissionStatusChanged;
use App\Events\DealAmountUpdated;
use App\Events\DealCreated as DealCreatedEvent;
use App\Events\DealDeclined;
use App\Events\DealReferrerAssigned;
use App\Events\DealStageMoved;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // List view only needs commission splits for the UI.
        // history/notes/attachments/links are NOT shown on the list — load them only in show().
        // Removing history prevents a crash if lead_history table is missing.
        $relations = ['commissionSplits'];

        // Include partner associations when explicitly requested (Referrer portal deal list).
        $includePartners = $request->boolean('include_partners');
        if ($includePartners) {
            $relations[] = 'dealPartners.partner';
        }

        // reseller_id column may not exist on all DB deployments — check once per day and add
        // it to the select only if present; leads still load without it (uses reseller_name)
        $baseSelect = [
            'id', 'tenant_id', 'name', 'stage', 'status', 'days_left',
            'reseller_name', 'organization_id',
            'commission_status', 'base_cost', 'added_amount', 'deal_value',
            'data',
            'created_at', 'updated_at', 'deleted_at', 'deleted_by',
        ];
        if (Cache::remember('schema.leads_has_reseller_id', 86400, fn() =>
            \Illuminate\Support\Facades\Schema::hasColumn('leads', 'reseller_id')
        )) {
            $baseSelect[] = 'reseller_id';
        }

        $query = Lead::with($relations)
            ->select($baseSelect)
            ->orderBy('created_at', 'desc');

        // Derive tenant from authenticated context; fall back to query param
        $tenantId = TenantContext::id() ?? $request->query('tenant_id');
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif (!TenantContext::isSuperAdmin()) {
            abort(403, 'Tenant context required.');
        }

        if ($request->filled('reseller_name')) {
            $query->forResellerOrSplit($request->reseller_name);
        }

        // Archived deals are excluded by default; pass status=archived to show only archived.
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        } else {
            $query->where('status', '!=', 'archived');
        }

        // Paginate to prevent OOM on large tenants; callers may request all via per_page=all
        $perPage = $request->get('per_page', 200); // default raised from 50 → 200
        if ($perPage === 'all' && TenantContext::isSuperAdmin()) {
            $leads = $query->get();
        } else {
            $perPage = min(500, max(1, (int) $perPage));
            $paginated = $query->paginate($perPage);
            $leads     = $paginated->getCollection();
        }

        // Enrich with last_activity_at from lead_history — separate query so the main
        // paginate never crashes if the table is missing (Supabase may not have it).
        try {
            $pageIds = $leads->pluck('id')->all();
            if (!empty($pageIds)) {
                $lastActivities = DB::table('lead_history')
                    ->whereIn('lead_id', $pageIds)
                    ->selectRaw('lead_id, MAX(created_at) as last_activity_at')
                    ->groupBy('lead_id')
                    ->pluck('last_activity_at', 'lead_id');

                $leads = $leads->map(function ($lead) use ($lastActivities) {
                    $lead->last_activity_at = $lastActivities->get($lead->id);
                    return $lead;
                });
            }
        } catch (\Throwable) {
            // Table absent — last_activity_at stays null on each lead
        }

        // Enrich with has_notes — batch query so the main load is not affected.
        // has_notes=true when any shared deal_comment (not deleted, no parent) OR any lead_note exists.
        try {
            $pageIds = $leads->pluck('id')->all();
            if (!empty($pageIds)) {
                $withComments = DB::table('deal_comments')
                    ->whereIn('lead_id', $pageIds)
                    ->where('visibility', 'shared')
                    ->whereNull('deleted_at')
                    ->whereNull('parent_comment_id')
                    ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->distinct()
                    ->pluck('lead_id')
                    ->flip();

                $withLegacyNotes = DB::table('lead_notes')
                    ->whereIn('lead_id', $pageIds)
                    // lead_notes has no tenant_id column; whereIn($pageIds) already scopes to tenant's leads
                    ->distinct()
                    ->pluck('lead_id')
                    ->flip();

                $leads = $leads->map(function ($lead) use ($withComments, $withLegacyNotes) {
                    $id = is_array($lead) ? ($lead['id'] ?? null) : $lead->id;
                    $hasNotes = isset($withComments[$id]) || isset($withLegacyNotes[$id]);
                    if (is_array($lead)) {
                        $lead['has_notes'] = $hasNotes;
                    } else {
                        $lead->has_notes = $hasNotes;
                    }
                    return $lead;
                });
            }
        } catch (\Throwable) {
            // Table absent or query failed — has_notes stays absent on each lead
        }

        if ($includePartners) {
            try {
                $calc = app(\App\Services\CommissionCalculationService::class);

                $leadIds = $leads->pluck('id')->all();
                $partnerSplitsByDeal = collect();
                if (!empty($leadIds)) {
                    $partnerSplitsByDeal = DB::table('deal_partner_splits')
                        ->whereIn('deal_id', $leadIds)
                        ->whereNull('deleted_at')
                        ->where('status', '!=', 'removed')
                        ->select('deal_id', 'split_share_value', 'split_share_type')
                        ->get()
                        ->groupBy('deal_id');
                }

                $leads = $leads->map(function ($lead) use ($calc, $partnerSplitsByDeal) {
                    $data = $lead instanceof Lead ? $lead->toArray() : (array) $lead;

                    try {
                        $data['partners'] = ($lead instanceof Lead ? $lead->dealPartners : collect())
                            ->where('status', 'active')
                            ->map(fn(DealPartner $dp) => [
                                'id'             => $dp->partner_user_id,
                                'display_name'   => $dp->partner?->display_name ?? null,
                                'email'          => $dp->partner?->email ?? null,
                                'setup_complete' => $dp->partner?->isSetupComplete() ?? false,
                            ])
                            ->values()
                            ->toArray();
                    } catch (\Throwable) {
                        $data['partners'] = [];
                    }

                    try {
                        $breakdown    = $lead instanceof Lead ? $calc->breakdownFromLead($lead) : [];
                        $pool         = (float) ($breakdown['commission_pool'] ?? 0);
                        $splits       = $partnerSplitsByDeal->get($lead->id ?? ($data['id'] ?? null), collect());
                        $partnerTotal = $splits->sum(
                            fn ($ps) => $calc->partnerShare($pool, (float) $ps->split_share_value, $ps->split_share_type ?? 'percentage')
                        );
                    } catch (\Throwable) {
                        $pool = 0.0;
                        $partnerTotal = 0.0;
                    }

                    $data['commission_pool']          = round($pool, 2);
                    $data['partner_commission_total'] = round($partnerTotal, 2);
                    $data['referrer_pool_remaining']  = max(0.0, round($pool - $partnerTotal, 2));

                    return $data;
                });
            } catch (\Throwable $e) {
                \Log::warning('LeadController include_partners failed: ' . $e->getMessage(), [
                    'file' => $e->getFile(), 'line' => $e->getLine(),
                ]);
            }
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
            return response()->json(['message' => 'An unexpected error occurred. Please try again.'], 500);
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

        // ── Set days_left from pipeline stage configuration ───────────
        $daysLeft = $this->resolveStageLimit($tenantId, $data['stage']) ?? ($data['days_left'] ?? 21);

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
        // LOCKED RULE — applied when deal_value is still 0 after financial derivation.
        // base_cost and added_amount are computed immediately from the default amount
        // so that commission pool is 70% of added_amount (not 70% of full deal value).
        $amountWasDefaulted = false;
        if ($tenantId === 'lgu-ids' && $dealValue == 0) {
            $dealValue          = 4_000_000.00;
            $baseCost           = \App\Services\LguIds\LguIdsPricingService::lookupBaseCost($dealValue);
            $addedAmount        = $dealValue - $baseCost;
            $amountWasDefaulted = true;
        }

        $leadData = $data['data'] ?? [];
        if ($amountWasDefaulted) {
            $leadData['amount_defaulted']             = true;
            $leadData['amount_confirmation_status']   = 'pending';
            $leadData['amount_default_reason']        = 'LGU IDS default applied — no deal amount was provided.';
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
            'data'              => $leadData,
        ]);

        // Bust the reseller activity log + performance caches so new deal appears immediately.
        // Use canonical DB name (not raw input) so the key matches CriticalActionService's cache key.
        if (!empty($data['reseller_name'])) {
            $storeReseller = Reseller::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($data['reseller_name'])])
                ->select('id', 'name')
                ->first();
            $rid = $storeReseller?->id;
            $canonicalStoreName = $storeReseller?->name ?? $data['reseller_name'];
            if ($rid) {
                Cache::forget("reseller_leadids:{$tenantId}:{$rid}");
                Cache::forget("referrer_perf:{$tenantId}:{$rid}");
            }
            Cache::forget("ca_reseller:{$tenantId}:" . md5($canonicalStoreName . ':' . ($rid ?? '')));
        }

        if ($amountWasDefaulted) {
            try {
                app(\App\Services\DealActivityService::class)->record(
                    $lead,
                    'LGU IDS default deal amount of ₱4,000,000 applied — no deal amount was provided.',
                    'amount',
                    [
                        'category'   => 'financial',
                        'actor_name' => 'System',
                        'actor_role' => 'system',
                        'new_values' => ['deal_value' => 4000000.00, 'amount_source' => 'lgu_ids_default'],
                    ]
                );
            } catch (\Throwable) {}
        }

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

        // LGU IDS: auto-create referrer note task if deal starts in a target stage
        if ($lead->tenant_id === 'lgu-ids') {
            try {
                app(\App\Services\LguIds\LguIdsDealNoteTaskService::class)
                    ->createForDeal($lead, 'deal_created');
            } catch (\Throwable) {}
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

        try { app(\App\Services\CriticalActionService::class)->invalidateCache($lead->tenant_id); } catch (\Throwable) {}

        return response()->json(array_merge(
            $lead->load(['commissionSplits', 'notes', 'history'])->toArray(),
            [
                'reseller_created' => $resellerCreated,
                'invite_sent'      => $inviteSent,
                'invite_action'    => $inviteAction,
            ]
        ), 201);
    } // end doStore

    /**
     * Returns the days_left for a given stage in a given tenant, or null if unconfigured.
     * LGU IDS uses tenant_pipeline_stage_rules (LOCKED). All others use tenant_pipeline_stages.
     */
    private function resolveStageLimit(string $tenantId, string $stage): ?int
    {
        $map = Cache::remember("stage_limits_{$tenantId}", 600, function () use ($tenantId) {
            if ($tenantId === 'lgu-ids') {
                return DB::table('tenant_pipeline_stage_rules')
                    ->where('tenant_id', 'lgu-ids')
                    ->pluck('max_days', 'stage')
                    ->toArray();
            }
            return DB::table('tenant_pipeline_stages')
                ->where('tenant_id', $tenantId)
                ->pluck('days_limit', 'stage_key')
                ->toArray();
        });
        $val = $map[$stage] ?? null;
        return $val !== null ? (int) $val : null;
    }

    private function callerIsTenantAdmin(): bool
    {
        return Auth::guard('web')->check()
            || Auth::guard('tenant')->check()
            || (Auth::guard('sanctum')->check() && Auth::guard('sanctum')->user() instanceof \App\Models\User);
    }

    private function resolveActor(): array
    {
        if (\Illuminate\Support\Facades\Auth::guard('tenant')->check()) {
            $u = \Illuminate\Support\Facades\Auth::guard('tenant')->user();
            return [$u->id ?? null, 'Tenant Admin', $u->full_name ?? $u->email ?? 'Admin'];
        }
        if (\Illuminate\Support\Facades\Auth::guard('web')->check()) {
            $u = \Illuminate\Support\Facades\Auth::guard('web')->user();
            return [$u->id ?? null, 'Super Admin', $u->name ?? $u->email ?? 'Super Admin'];
        }
        if (\Illuminate\Support\Facades\Auth::guard('reseller')->check()) {
            $u = \Illuminate\Support\Facades\Auth::guard('reseller')->user();
            return [$u->id ?? null, 'Referrer', $u->name ?? 'Referrer'];
        }
        return [null, 'System', 'System'];
    }

    /**
     * Notify the Referrer assigned to a deal. Never throws — best-effort only.
     */
    private function notifyAssignedReferrer(Lead $lead, string $title, string $body, string $dedupSuffix): void
    {
        try {
            if (!$lead->reseller_name) return;
            $reseller = Reseller::where('tenant_id', $lead->tenant_id)
                ->whereRaw('LOWER(name) = ?', [strtolower($lead->reseller_name)])
                ->first();
            if (!$reseller) return;
            app(NotificationDispatchService::class)->dispatchToReseller(
                resellerId:   (string) $reseller->id,
                tenantId:     $lead->tenant_id,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        $title,
                body:         $body,
                actionUrl:    "/reseller/{$lead->tenant_id}/deals/{$lead->id}",
                actionLabel:  'View Deal',
                dedupeSuffix: $dedupSuffix,
            );
        } catch (\Throwable) {}
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

        // ── Reset days_left when stage advances ──────────────────────
        if (isset($data['stage']) && $data['stage'] !== $lead->stage) {
            $limit = $this->resolveStageLimit($lead->tenant_id, $data['stage']);
            if ($limit !== null) {
                $data['days_left'] = $limit;
                $data['status']    = 'active';
            }
        }

        $oldResellerName     = $lead->reseller_name ?? '';
        $oldCommissionStatus = $lead->commission_status;
        $oldStageForEvent    = $lead->stage          ?? '';
        $oldStatusForEvent   = $lead->status         ?? '';
        $lead->update($data);

        // Bust CA cache when reseller, commission status, or stage changes
        if (isset($data['reseller_name']) || isset($data['commission_status']) || isset($data['stage'])) {
            foreach (array_unique(array_filter([$oldResellerName, $lead->reseller_name ?? ''])) as $rName) {
                $rid = Reseller::where('tenant_id', $lead->tenant_id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($rName)])
                    ->value('id');
                Cache::forget("ca_reseller:{$lead->tenant_id}:" . md5($rName . ':' . ($rid ?? '')));
            }
            [$actorIdForCache] = $this->resolveActor();
            try { app(\App\Services\CriticalActionService::class)->invalidateCache($lead->tenant_id, (string) $actorIdForCache); } catch (\Throwable) {}
        }

        // Fire commission status event AFTER the DB write succeeds
        $newCommissionStatus = $lead->fresh()->commission_status;
        if (isset($data['commission_status']) && $newCommissionStatus !== $oldCommissionStatus) {
            CommissionStatusChanged::dispatch(
                leadId:       $lead->id,
                leadName:     $lead->name,
                tenantId:     $lead->tenant_id,
                resellerName: $lead->reseller_name,
                newStatus:    $newCommissionStatus,
                dealValue:    (float) ($lead->deal_value ?? 0),
            );
        }

        // Resolve actor once — used by stage-moved and declined notifications below
        $needsActorForNotify = (isset($data['stage']) && $data['stage'] !== $oldStageForEvent && !empty($lead->reseller_name))
                            || (isset($data['status']) && $data['status'] === 'declined' && $oldStatusForEvent !== 'declined' && !empty($lead->reseller_name));

        $actorNameForNotify = null;
        if ($needsActorForNotify) {
            [, , $actorNameForNotify] = $this->resolveActor();
        }

        // Stage moved by admin — notify assigned reseller
        if (isset($data['stage']) && $data['stage'] !== $oldStageForEvent && !empty($lead->reseller_name)) {
            DealStageMoved::dispatch(
                leadId:       $lead->id,
                leadName:     $lead->name,
                tenantId:     $lead->tenant_id,
                resellerName: $lead->reseller_name,
                fromStage:    $oldStageForEvent,
                toStage:      $data['stage'],
                dealValue:    (float) ($lead->deal_value ?? 0),
                movedByName:  $actorNameForNotify ?? 'Admin',
            );
        }

        // Deal declined by admin — notify only on the transition (not on repeat updates)
        if (isset($data['status']) && $data['status'] === 'declined' && $oldStatusForEvent !== 'declined' && !empty($lead->reseller_name)) {
            DealDeclined::dispatch(
                leadId:        $lead->id,
                leadName:      $lead->name,
                tenantId:      $lead->tenant_id,
                resellerName:  $lead->reseller_name,
                stage:         $lead->stage,
                dealValue:     (float) ($lead->deal_value ?? 0),
                declinedByName: $actorNameForNotify ?? 'Admin',
            );
        }

        // ── Audit: log financial changes to deal history ──────────────
        $newDealValue   = (float) ($lead->deal_value   ?? 0);
        $newBaseCost    = (float) ($lead->base_cost     ?? 0);
        $newAddedAmount = (float) ($lead->added_amount  ?? 0);

        $financialChanged = abs($newDealValue - $oldDealValue)     > 0.01
                         || abs($newBaseCost - $oldBaseCost)       > 0.01
                         || abs($newAddedAmount - $oldAddedAmount) > 0.01;

        if ($financialChanged) {
            [$actorId, $actorRole, $actorName] = $this->resolveActor();

            // Email assigned reseller that the deal amount changed
            if (!empty($lead->reseller_name)) {
                DealAmountUpdated::dispatch(
                    leadId:        $lead->id,
                    leadName:      $lead->name,
                    tenantId:      $lead->tenant_id,
                    resellerName:  $lead->reseller_name,
                    oldAmount:     $oldDealValue,
                    newAmount:     (float) ($lead->deal_value ?? 0),
                    updatedByName: $actorName ?? 'Admin',
                );
            }

            app(\App\Services\DealActivityService::class)->financialBreakdownChanged(
                $lead,
                ['deal_value' => $oldDealValue,   'base_cost' => $oldBaseCost,   'added_amount' => $oldAddedAmount],
                ['deal_value' => $newDealValue,    'base_cost' => $newBaseCost,   'added_amount' => $newAddedAmount],
                $actorRole,
            );

            // If this deal had a defaulted amount pending confirmation, mark as updated
            $freshData = $lead->data ?? [];
            if (($freshData['amount_defaulted'] ?? false) && ($freshData['amount_confirmation_status'] ?? '') === 'pending') {
                $freshData['amount_confirmation_status'] = 'updated';
                $freshData['amount_confirmed_at']        = now()->toIso8601String();
                $freshData['amount_confirmed_by']        = $actorName ?? 'Admin';
                $lead->update(['data' => $freshData]);

                try {
                    app(\App\Services\DealActivityService::class)->record(
                        $lead,
                        ($actorName ?? 'Admin') . ' changed the deal amount from the LGU IDS default ₱4,000,000 to ₱' . number_format($newDealValue, 0) . '.',
                        'amount',
                        [
                            'category'   => 'financial',
                            'actor_name' => $actorName ?? 'Admin',
                            'actor_role' => $actorRole,
                            'old_values' => ['deal_value' => 4000000.00, 'amount_source' => 'lgu_ids_default'],
                            'new_values' => ['deal_value' => $newDealValue, 'amount_source' => 'user_updated'],
                        ]
                    );
                } catch (\Throwable) {}

                if ($isReferrer) {
                    $this->notifyAssignedReferrer($lead, 'Deal amount updated by Referrer',
                        ($actorName ?? 'Referrer') . ' changed "' . $lead->name . '" from the default ₱4,000,000 to ₱' . number_format($newDealValue, 0) . '.',
                        $lead->id . ':default_updated:' . now()->format('Ymd'));
                }
            }

            // Notify assigned referrer when admin/manager changes the deal amount
            if (!$isReferrer && !$isPartner) {
                $this->notifyAssignedReferrer(
                    $lead,
                    'Deal amount updated',
                    ($actorName ?? 'Admin') . ' updated the contract value of "' . $lead->name . '" to ₱' . number_format($newDealValue, 0) . '.',
                    $lead->id . ':amount:' . now()->format('Ymd'),
                );
            }
        }

        return response()->json(
            $lead->fresh(['commissionSplits', 'notes'])
                 ->load(['history' => fn($q) => $q->orderByDesc('created_at')->limit(50)])
        );
    }

    public function destroy(Lead $lead): JsonResponse
    {
        if (!$this->callerIsTenantAdmin()) {
            return response()->json(['error' => 'Only admins can delete deals.'], 403);
        }

        $lead->assertBelongsToCurrentTenant();

        [$actorId, $actorRole, $actorName] = $this->resolveActor();
        $leadId   = $lead->id;
        $leadName = $lead->name;
        $tenantId = $lead->tenant_id;

        // Soft-delete: moves deal to archive (10-day recovery window)
        $lead->deleted_by = $actorName;
        $lead->save();
        $lead->delete();
        Cache::forget("dash_counts:{$tenantId}");
        try { app(\App\Services\CriticalActionService::class)->invalidateCache($tenantId); } catch (\Throwable) {}

        Log::info('Deal archived (soft-deleted)', [
            'lead_id'    => $leadId,
            'lead_name'  => $leadName,
            'tenant_id'  => $tenantId,
            'deleted_by' => $actorName,
        ]);

        // Notify admins and the assigned referrer
        try {
            $notifSvc = app(\App\Services\NotificationDispatchService::class);
            $notifSvc->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Deal archived',
                body:         "{$leadName} was moved to the archive by {$actorName}.",
                actionUrl:    "/tenant/{$tenantId}/deals",
                actionLabel:  'View Deals',
                dedupeSuffix: "deal_archived:{$leadId}",
            );
            if ($lead->reseller_name) {
                $reseller = \App\Models\Reseller::where('tenant_id', $tenantId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($lead->reseller_name)])
                    ->first();
                if ($reseller) {
                    $notifSvc->dispatchToReseller(
                        resellerId:   $reseller->id,
                        tenantId:     $tenantId,
                        category:     'deal_pipeline',
                        priority:     'normal',
                        title:        'One of your deals was archived',
                        body:         "{$leadName} has been moved to the archive.",
                        actionUrl:    "/reseller/{$tenantId}/deals",
                        actionLabel:  'View My Deals',
                        dedupeSuffix: "deal_archived_rs:{$leadId}",
                    );
                }
            }
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'deleted_id' => $leadId]);
    }

    public function archivedIndex(Request $request): JsonResponse
    {
        if (!$this->callerIsTenantAdmin()) {
            return response()->json(['error' => 'Only admins can view archived deals.'], 403);
        }

        $tenantId = TenantContext::id() ?? $request->query('tenant_id');
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant context required.'], 403);
        }

        // Soft-deleted leads (hard-archived by admin)
        $softDeleted = Lead::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->select(['id', 'tenant_id', 'name', 'stage', 'status', 'reseller_name', 'deal_value', 'commission_status', 'deleted_at', 'deleted_by'])
            ->orderBy('deleted_at', 'desc')
            ->limit(500)
            ->get()
            ->map(function (Lead $lead) {
                $arr = $lead->toArray();
                $daysSince = (int) $lead->deleted_at->diffInDays(now());
                $arr['days_until_purge'] = max(0, 10 - $daysSince);
                $arr['archive_type'] = 'deleted';
                return $arr;
            });

        // Status-archived leads (approved archive requests — not soft-deleted)
        $statusArchived = Lead::where('tenant_id', $tenantId)
            ->where('status', 'archived')
            ->select(['id', 'tenant_id', 'name', 'stage', 'status', 'reseller_name', 'deal_value', 'commission_status', 'updated_at'])
            ->orderBy('updated_at', 'desc')
            ->limit(500)
            ->get()
            ->map(function (Lead $lead) {
                $arr = $lead->toArray();
                $arr['deleted_at']      = $lead->updated_at; // approximation for display
                $arr['deleted_by']      = null;
                $arr['days_until_purge'] = null;
                $arr['archive_type']    = 'status_archived';
                return $arr;
            });

        $leads = $softDeleted->concat($statusArchived)
            ->sortByDesc('deleted_at')
            ->values();

        return response()->json($leads);
    }

    public function restore(Request $request, string $leadId): JsonResponse
    {
        if (!$this->callerIsTenantAdmin()) {
            return response()->json(['error' => 'Only admins can restore deals.'], 403);
        }

        $tenantId = TenantContext::id() ?? $request->query('tenant_id');

        // Check status-archived first (not soft-deleted — approved via archive request)
        $statusArchived = Lead::where('tenant_id', $tenantId)
            ->where('id', $leadId)
            ->where('status', 'archived')
            ->first();

        if ($statusArchived) {
            $statusArchived->update(['status' => 'active']);

            // Log activity
            try {
                app(\App\Services\DealActivityService::class)->record(
                    $statusArchived,
                    'Deal reactivated by admin — status changed from archived to active.',
                    'deal',
                );
            } catch (\Throwable) {}

            // Notify assigned referrer
            try {
                $reseller = \App\Models\Reseller::where('tenant_id', $statusArchived->tenant_id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($statusArchived->reseller_name ?? '')])
                    ->first();
                if ($reseller) {
                    app(NotificationDispatchService::class)->dispatchToReseller(
                        resellerId:   (string) $reseller->id,
                        tenantId:     $statusArchived->tenant_id,
                        category:     'deal_pipeline',
                        priority:     'high',
                        title:        'Your deal has been reactivated',
                        body:         '"' . $statusArchived->name . '" has been reactivated by an admin and is now active again.',
                        actionUrl:    "/reseller/{$statusArchived->tenant_id}/deals/{$statusArchived->id}",
                        actionLabel:  'View Deal',
                        dedupeSuffix: $statusArchived->id . ':reactivated:' . now()->format('Ymd'),
                    );
                }
            } catch (\Throwable) {}

            return response()->json(['success' => true, 'message' => 'Deal reactivated.']);
        }

        $lead = Lead::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('id', $leadId)
            ->first();

        if (!$lead) {
            return response()->json(['error' => 'Archived deal not found.'], 404);
        }

        $lead->restore();
        $lead->update(['deleted_by' => null]);
        Cache::forget("dash_counts:{$tenantId}");

        [$actorId, $actorRole, $actorName] = $this->resolveActor();

        try {
            app(\App\Services\DealActivityService::class)->record(
                $lead,
                ($actorName ?? 'Admin') . ' restored this deal from the archive.',
                'assignment',
                ['category' => 'deal', 'actor_name' => $actorName ?? 'Admin', 'actor_role' => $actorRole]
            );
        } catch (\Throwable) {}

        Log::info('Deal restored from archive', [
            'lead_id'     => $lead->id,
            'tenant_id'   => $lead->tenant_id,
            'restored_by' => $actorName,
        ]);

        // Bust CA cache so restored deal surfaces immediately for the reseller
        if ($lead->reseller_name) {
            $rid = \App\Models\Reseller::where('tenant_id', $lead->tenant_id)
                ->whereRaw('LOWER(name) = ?', [strtolower($lead->reseller_name)])
                ->value('id');
            Cache::forget("ca_reseller:{$lead->tenant_id}:" . md5($lead->reseller_name . ':' . ($rid ?? '')));
        }

        // Notify admins and the assigned referrer
        try {
            $notifSvc = app(\App\Services\NotificationDispatchService::class);
            $notifSvc->dispatchToTenantAdmins(
                tenantId:     $lead->tenant_id,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Deal restored from archive',
                body:         "{$lead->name} has been restored and is now active again.",
                actionUrl:    "/tenant/{$lead->tenant_id}/deals/{$lead->id}",
                actionLabel:  'View Deal',
                dedupeSuffix: "deal_restored:{$lead->id}",
            );
            if ($lead->reseller_name) {
                $reseller = \App\Models\Reseller::where('tenant_id', $lead->tenant_id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($lead->reseller_name)])
                    ->first();
                if ($reseller) {
                    $notifSvc->dispatchToReseller(
                        resellerId:   $reseller->id,
                        tenantId:     $lead->tenant_id,
                        category:     'deal_pipeline',
                        priority:     'normal',
                        title:        'Your deal has been restored',
                        body:         "{$lead->name} was restored from the archive and is active again.",
                        actionUrl:    "/reseller/{$lead->tenant_id}/deals/{$lead->id}",
                        actionLabel:  'View Deal',
                        dedupeSuffix: "deal_restored_rs:{$lead->id}",
                    );
                }
            }
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'lead' => $lead->fresh(['commissionSplits'])]);
    }

    public function forceDeleteLead(Request $request, string $leadId): JsonResponse
    {
        if (!$this->callerIsTenantAdmin()) {
            return response()->json(['error' => 'Only admins can permanently delete deals.'], 403);
        }

        $tenantId = TenantContext::id() ?? $request->query('tenant_id');
        $lead = Lead::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('id', $leadId)
            ->first();

        if (!$lead) {
            return response()->json(['error' => 'Archived deal not found.'], 404);
        }

        [$actorId, $actorRole, $actorName] = $this->resolveActor();
        $deletedId   = $lead->id;
        $leadName    = $lead->name;
        $lead->forceDelete();

        Log::info('Deal permanently deleted (force)', [
            'lead_id'    => $deletedId,
            'lead_name'  => $leadName,
            'tenant_id'  => $tenantId,
            'deleted_by' => $actorName,
        ]);

        return response()->json(['success' => true, 'deleted_id' => $deletedId]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        if (!$this->callerIsTenantAdmin()) {
            return response()->json(['error' => 'Only admins can delete deals.'], 403);
        }

        $tenantId = TenantContext::id() ?? $request->input('tenant_id');
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant context required.'], 403);
        }

        $data = $request->validate([
            'ids'   => 'required|array|min:1|max:200',
            'ids.*' => 'required|string|uuid',
        ]);

        [$actorId, $actorRole, $actorName] = $this->resolveActor();

        $affectedLeads = Lead::where('tenant_id', $tenantId)
            ->whereIn('id', $data['ids'])
            ->select('id', 'name', 'reseller_name')
            ->get();

        $count = $affectedLeads->count();

        Lead::where('tenant_id', $tenantId)
            ->whereIn('id', $data['ids'])
            ->update(['deleted_by' => $actorName, 'deleted_at' => now()]);

        Log::info('Bulk deal archive (soft-delete)', [
            'tenant_id'  => $tenantId,
            'count'      => $count,
            'deleted_by' => $actorName,
        ]);

        Cache::forget("dash_counts:{$tenantId}");
        try { app(\App\Services\CriticalActionService::class)->invalidateCache($tenantId); } catch (\Throwable) {}

        // Notify each unique Referrer who had deals archived — batch Reseller lookup to avoid N+1
        $byReseller = $affectedLeads->whereNotNull('reseller_name')->groupBy(fn($l) => strtolower(trim($l->reseller_name)));
        $uniqueLowerNames = $byReseller->keys()->map(fn($n) => strtolower($n))->all();
        $resellerMap = \App\Models\Reseller::where('tenant_id', $tenantId)
            ->whereIn(DB::raw('LOWER(name)'), $uniqueLowerNames)
            ->whereIn('status', ['active', 'nda_signed'])
            ->get()
            ->keyBy(fn($r) => strtolower($r->name));

        foreach ($byReseller as $resellerName => $deals) {
            try {
                $reseller = $resellerMap[strtolower($resellerName)] ?? null;
                if (!$reseller) continue;
                $n = $deals->count();
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   (string) $reseller->id,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'high',
                    title:        $n === 1 ? "Your deal was archived" : "{$n} of your deals were archived",
                    body:         $n === 1
                        ? "\"{$deals->first()->name}\" was moved to the archive by {$actorName}."
                        : "{$n} deals assigned to you were moved to the archive by {$actorName}.",
                    actionUrl:    "/reseller/{$tenantId}/deals",
                    actionLabel:  'View Deals',
                    dedupeSuffix: "bulk_archive:{$tenantId}:{$reseller->id}:" . now()->format('YmdH'),
                );
            } catch (\Throwable) {}
        }

        // Notify Tenant Admins once with the aggregate count
        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        $count === 1 ? "Deal archived" : "{$count} deals archived",
                body:         $count === 1
                    ? "\"{$affectedLeads->first()->name}\" was moved to the archive by {$actorName}."
                    : "{$count} deals were moved to the archive by {$actorName}.",
                actionUrl:    "/tenant/{$tenantId}/deals",
                actionLabel:  'View Deals',
                dedupeSuffix: "bulk_archive_admin:{$tenantId}:" . now()->format('YmdH'),
            );
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'deleted_count' => $count]);
    }

    /**
     * POST /api/leads/{lead}/confirm-default-amount
     * Confirm the LGU IDS default deal amount of ₱4,000,000.
     */
    public function confirmDefaultAmount(Request $request, Lead $lead): JsonResponse
    {
        $lead->assertBelongsToCurrentTenant();

        $currentData = $lead->data ?? [];
        if (!($currentData['amount_defaulted'] ?? false)) {
            return response()->json(['message' => 'This deal does not have a pending default amount.'], 422);
        }
        if (($currentData['amount_confirmation_status'] ?? '') !== 'pending') {
            return response()->json(['message' => 'Amount confirmation already resolved.'], 422);
        }

        [$actorId, $actorRole, $actorName] = $this->resolveActor();

        $currentData['amount_confirmation_status'] = 'confirmed';
        $currentData['amount_confirmed_at']         = now()->toIso8601String();
        $currentData['amount_confirmed_by']         = $actorName ?? 'Admin';
        $lead->update(['data' => $currentData]);

        try {
            app(\App\Services\DealActivityService::class)->record(
                $lead,
                ($actorName ?? 'Admin') . ' confirmed the LGU IDS default deal amount of ₱4,000,000.',
                'amount',
                [
                    'category'   => 'financial',
                    'actor_name' => $actorName ?? 'Admin',
                    'actor_role' => $actorRole,
                    'new_values' => ['deal_value' => 4000000.00, 'amount_source' => 'lgu_ids_default_confirmed'],
                ]
            );
        } catch (\Throwable) {}

        // Notify admins if a referrer confirmed
        if (\Illuminate\Support\Facades\Auth::guard('reseller')->check()) {
            $this->notifyAssignedReferrer(
                $lead,
                'Default amount confirmed',
                ($actorName ?? 'Referrer') . ' confirmed the ₱4,000,000 default amount on "' . $lead->name . '".',
                $lead->id . ':default_confirmed:referrer',
            );
            try {
                app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                    tenantId:     $lead->tenant_id,
                    category:     'deal_pipeline',
                    priority:     'normal',
                    title:        'Default deal amount confirmed',
                    body:         ($actorName ?? 'Referrer') . ' confirmed the ₱4,000,000 default amount for "' . $lead->name . '".',
                    actionUrl:    "/tenant/{$lead->tenant_id}/deals/{$lead->id}",
                    actionLabel:  'View Deal',
                    dedupeSuffix: $lead->id . ':default_confirmed_by_referrer',
                );
            } catch (\Throwable) {}
        }

        return response()->json(['success' => true, 'message' => 'Default amount confirmed.']);
    }

    public function moveStage(Request $request, Lead $lead): JsonResponse
    {
        // Partners are read-only — they cannot advance pipeline stages
        if (Auth::guard('partner')->check()) {
            return response()->json(['error' => 'Partners cannot modify deal stages.'], 403);
        }

        $lead->assertBelongsToCurrentTenant();

        // Role-based permission: admin/owner/manager can move any deal;
        // referrers can only move stages for deals assigned to them.
        if (Auth::guard('reseller')->check()) {
            $referrer = Auth::guard('reseller')->user();
            if ((string) $lead->reseller_id !== (string) $referrer->id
                && $lead->reseller_name !== $referrer->name) {
                return response()->json(['error' => 'You can only advance stages on deals assigned to you.'], 403);
            }
        }

        $stages       = ['introduction', 'presentation', 'contract_sent', 'signed', 'paid'];
        $currentIndex = (int) array_search($lead->stage, $stages, true);

        // ── Terminal stage guard ──────────────────────────────────────
        if ($lead->stage === 'paid') {
            return response()->json(['error' => 'This deal is already at the final stage (Paid) and cannot be advanced further.'], 422);
        }

        // Accept explicit target stage OR advance to next in sequence
        if ($request->filled('stage') && in_array($request->stage, $stages, true)) {
            $targetStage = $request->stage;
        } else {
            if ($currentIndex >= count($stages) - 1) {
                return response()->json(['error' => 'Already at final stage.'], 422);
            }
            $targetStage = $stages[$currentIndex + 1];
        }

        $targetIndex = (int) array_search($targetStage, $stages, true);

        if ($targetStage === $lead->stage) {
            return response()->json(['error' => 'Deal is already at this stage.'], 422);
        }

        // ── Forward-only guard: prevent backward/lateral stage moves ──
        if ($targetIndex <= $currentIndex) {
            return response()->json([
                'error' => 'Stage moves must progress forward. You cannot move a deal to an earlier stage.',
            ], 422);
        }

        // ── Commission lock guard ─────────────────────────────────────
        // Once commission is locked (Signed), the only valid move is to Paid.
        if ($lead->commission_status === 'locked' && $targetStage !== 'paid') {
            return response()->json([
                'error' => 'Commission is locked for this deal. The only valid move from Signed is to Paid.',
            ], 422);
        }

        $isLocking     = $targetStage === 'signed';
        $isPaid        = $targetStage === 'paid';
        $note          = trim((string) $request->input('note', ''));
        $capturedStage = $lead->stage;

        $updates = [
            'stage'             => $targetStage,
            'commission_status' => $isLocking ? 'locked' : ($isPaid ? 'paid' : $lead->commission_status),
        ];

        // ── Paid stage always resets status — prevents stuck expiring/expired in Critical Actions ──
        if ($isPaid) {
            $updates['status']    = 'active';
            $updates['days_left'] = 0;
        }

        // ── LGU IDS: reset days_left when stage advances ──────────────
        // LOCKED RULE — mirrors update() (LGU IDS pipeline protection)
        if ($lead->tenant_id === 'lgu-ids' && !$isPaid) {
            $stageLimit = $this->resolveStageLimit('lgu-ids', $targetStage);
            if ($stageLimit !== null) {
                $updates['days_left'] = $stageLimit;
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

        // Fire commission event after transaction — referrers/partners need this
        if ($isLocking || $isPaid) {
            try {
                CommissionStatusChanged::dispatch(
                    leadId:       $lead->id,
                    leadName:     $lead->name,
                    tenantId:     $lead->tenant_id,
                    resellerName: $lead->reseller_name,
                    newStatus:    $isPaid ? 'paid' : 'locked',
                    dealValue:    (float) ($lead->deal_value ?? 0),
                );
            } catch (\Throwable) {}
        }

        // Bust CA cache before notifications — guaranteed even if dispatch throws
        if ($lead->reseller_name) {
            $caRid = Reseller::where('tenant_id', $lead->tenant_id)
                ->whereRaw('LOWER(name) = ?', [strtolower($lead->reseller_name)])
                ->value('id');
            Cache::forget("ca_reseller:{$lead->tenant_id}:" . md5($lead->reseller_name . ':' . ($caRid ?? '')));
        }
        try { app(\App\Services\CriticalActionService::class)->invalidateCache($lead->tenant_id, (string) $actorIdStage); } catch (\Throwable) {}

        // Notify tenant admins and assigned referrer about stage movement
        try {
            $stageName  = ucwords(str_replace('_', ' ', $targetStage));
            $fromName   = ucwords(str_replace('_', ' ', $capturedStage));
            $priority   = ($isPaid || $isLocking) ? 'high' : 'normal';
            $minute     = now()->format('YmdH');

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
                    ->whereRaw('LOWER(name) = ?', [strtolower($lead->reseller_name)])
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

            // Notify active partners on this deal
            $partnerSplits = DB::table('deal_partner_splits')
                ->where('deal_id', $lead->id)
                ->where('tenant_id', $lead->tenant_id)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'removed')
                ->whereNotNull('partner_user_id')
                ->pluck('partner_user_id');

            foreach ($partnerSplits as $partnerUserId) {
                app(NotificationDispatchService::class)->dispatchToPartner(
                    partnerId:    (string) $partnerUserId,
                    tenantId:     $lead->tenant_id,
                    category:     'deal_pipeline',
                    priority:     $priority,
                    title:        "Deal moved to {$stageName}",
                    body:         "\"{$lead->name}\" has been moved from {$fromName} to {$stageName}.",
                    actionUrl:    "/partner/deals/{$lead->id}",
                    actionLabel:  'View Deal',
                    dedupeSuffix: "{$lead->id}:stage:{$targetStage}:p:{$minute}",
                );
            }
        } catch (\Throwable) {}

        // LGU IDS: create note task if deal directly moved to a target stage
        if ($lead->tenant_id === 'lgu-ids') {
            try {
                app(\App\Services\LguIds\LguIdsDealNoteTaskService::class)
                    ->createForDeal($lead->fresh(), 'direct_stage_move');
            } catch (\Throwable) {}
        }

        return response()->json(
            $lead->fresh(['commissionSplits'])
                 ->load(['history' => fn($q) => $q->orderByDesc('created_at')->limit(50)])
        );
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

        try {
            app(\App\Services\DealActivityService::class)->noteAdded($lead, $data['text'] ?? '', [
                'actor_name' => $data['author'] ?? null,
            ]);
        } catch (\Throwable) {}

        // LGU IDS: complete open note tasks when admin adds a LeadNote
        if ($lead->tenant_id === 'lgu-ids') {
            try {
                $actorName = $data['author'] ?? 'Admin';
                \App\Models\Task::where('tenant_id', $lead->tenant_id)
                    ->where('source_type', \App\Services\LguIds\LguIdsDealNoteTaskService::SOURCE_TYPE)
                    ->where('taskable_type', 'lead')
                    ->where('taskable_id', $lead->id)
                    ->whereIn('status', ['open', 'in_progress', 'waiting'])
                    ->whereNull('deleted_at')
                    ->each(function ($task) use ($lead, $actorName) {
                        $task->update([
                            'status'            => 'completed',
                            'completed_at'      => now(),
                            'completed_by_type' => 'system',
                            'completed_by_id'   => 'system',
                        ]);
                        \App\Models\TaskActivity::create([
                            'tenant_id'   => $lead->tenant_id,
                            'task_id'     => $task->id,
                            'actor_type'  => 'system',
                            'actor_id'    => 'system',
                            'actor_name'  => $actorName,
                            'action_type' => 'task_completed',
                            'new_values'  => ['status' => 'completed', 'trigger' => 'admin_note_added'],
                        ]);
                    });
            } catch (\Throwable) {}
        }

        return response()->json($note, 201);
    }

    public function reassign(Request $request, Lead $lead): JsonResponse
    {
        if (!$this->callerIsTenantAdmin()) {
            return response()->json(['error' => 'Only admins can reassign deals.'], 403);
        }

        $lead->assertBelongsToCurrentTenant();

        $data = $request->validate([
            'reseller_name' => 'required|string',
            'reset_stage'   => 'boolean',
        ]);

        // Fetch the canonical reseller record — use their DB name to ensure portal
        // queries match exactly, regardless of how the admin typed the name.
        $newReseller = \App\Models\Reseller::where('tenant_id', $lead->tenant_id)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($data['reseller_name']))])
            ->whereIn('status', ['active', 'nda_signed', 'invited'])
            ->first();

        if (!$newReseller) {
            return response()->json(['error' => 'No active referrer found with that name in this workspace.'], 422);
        }

        $canonicalName   = $newReseller->name;
        $oldReferrerName = $lead->reseller_name ?? '';

        if (strtolower($canonicalName) === strtolower($oldReferrerName)) {
            return response()->json(['error' => 'This referrer is already assigned to this deal.'], 422);
        }

        // Compute new values before transaction so we capture pre-update model state
        $updatePayload = [
            'reseller_name'     => $canonicalName,
            'stage'             => ($data['reset_stage'] ?? true) ? 'introduction' : $lead->stage,
            'days_left'         => ($data['reset_stage'] ?? true)
                ? ($this->resolveStageLimit($lead->tenant_id, 'introduction') ?? ($lead->tenant_id === 'lgu-ids' ? 14 : 21))
                : $lead->days_left,
            'status'            => 'active',
            'commission_status' => 'pending',
        ];

        // Single atomic transaction: lead update + split replacement.
        // CommissionSplit has no tenant_id column — it is a child of Lead and inherits
        // tenant isolation via the lead_id FK. Tenant safety here relies on the
        // assertBelongsToCurrentTenant() guard above (line ~1168), which is the single
        // point of enforcement. Do NOT remove that guard without adding tenant_id to
        // commission_splits first.
        DB::transaction(function () use ($lead, $canonicalName, $updatePayload) {
            $lead->update($updatePayload);
            CommissionSplit::where('lead_id', $lead->id)->delete();
            CommissionSplit::create([
                'lead_id'         => $lead->id,
                'reseller_name'   => $canonicalName,
                'percentage'      => 100,
                'role'            => 'primary',
                'activity_status' => 'active',
            ]);
        });

        // $newReseller already fetched above for validation — reused here

        // Bust activity log + performance caches for both old and new resellers
        foreach (array_filter([$oldReferrerName, $canonicalName]) as $rName) {
            $rName = trim($rName);
            $rid = strtolower($rName) === strtolower($canonicalName)
                ? $newReseller->id
                : Reseller::where('tenant_id', $lead->tenant_id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($rName)])
                    ->value('id');
            if ($rid) {
                Cache::forget("reseller_leadids:{$lead->tenant_id}:{$rid}");
                Cache::forget("referrer_perf:{$lead->tenant_id}:{$rid}");
            }
            Cache::forget("ca_reseller:{$lead->tenant_id}:" . md5($rName . ':' . ($rid ?? '')));
        }
        try { app(\App\Services\CriticalActionService::class)->invalidateCache($lead->tenant_id); } catch (\Throwable) {}

        [$actorIdRa, $actorRoleRa, $actorNameRa] = $this->resolveActor();
        app(\App\Services\DealActivityService::class)->record($lead,
            'Referrer reassigned: ' . ($oldReferrerName ?: 'None')
                . ' \u{2192} ' . $canonicalName,
            'assignment',
            [
                'category'   => 'assignment',
                'actor_name' => $actorNameRa,
                'actor_role' => $actorRoleRa,
                'reseller'   => $canonicalName,
                'old_values' => ['referrer_name' => $oldReferrerName],
                'new_values' => ['referrer_name' => $canonicalName],
            ]
        );

        $assignmentType = ($oldReferrerName && $oldReferrerName !== $canonicalName)
            ? 'reassignment'
            : 'new_assignment';

        if ($newReseller->status === 'invited') {
            $assignmentType = 'pending_referrer_assignment';
        }

        DealReferrerAssigned::dispatch(
            leadId:          $lead->id,
            leadName:        $lead->name,
            tenantId:        $lead->tenant_id,
            resellerName:    $canonicalName,
            stage:           $lead->stage,
            dealValue:       (float) ($lead->deal_value ?? 0),
            assignmentType:  $assignmentType,
            resellerEmail:   $newReseller?->email,
            resellerId:      $newReseller ? (string) $newReseller->id : null,
            oldResellerName: $oldReferrerName ?: null,
            assignedByName:  $actorNameRa,
            assignedByRole:  $actorRoleRa,
        );

        // LGU IDS: create note task for new referrer if deal is in a target stage
        if ($lead->tenant_id === 'lgu-ids') {
            try {
                app(\App\Services\LguIds\LguIdsDealNoteTaskService::class)
                    ->createForDeal($lead->fresh(), 'referrer_reassigned');
            } catch (\Throwable) {}
        }

        return response()->json(
            $lead->fresh(['commissionSplits'])
                 ->load(['history' => fn($q) => $q->orderByDesc('created_at')->limit(50)])
        );
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

        // Batch-resolve canonical reseller names in one query (avoids N+1)
        $tenantIdForSplits = $lead->tenant_id;
        $lowerNames = array_unique(array_map(fn($s) => strtolower(trim($s['reseller_name'])), $data['splits']));
        $canonicalMap = Reseller::where('tenant_id', $tenantIdForSplits)
            ->whereIn(DB::raw('LOWER(name)'), $lowerNames)
            ->pluck('name', DB::raw('LOWER(name)'))
            ->toArray();

        $canonicalSplits = array_map(function (array $split) use ($canonicalMap) {
            $key = strtolower(trim($split['reseller_name']));
            $split['reseller_name'] = $canonicalMap[$key] ?? $split['reseller_name'];
            return $split;
        }, $data['splits']);

        DB::transaction(function () use ($lead, $canonicalSplits) {
            CommissionSplit::where('lead_id', $lead->id)->delete();
            foreach ($canonicalSplits as $split) {
                CommissionSplit::create(['lead_id' => $lead->id, ...$split]);
            }
        });

        LeadHistory::create([
            'lead_id' => $lead->id,
            'action'  => 'Commission split updated',
            'type'    => 'commission',
            'date'    => now()->toDateString(),
        ]);

        // Bust critical-actions cache for all affected referrers
        foreach ($canonicalSplits as $split) {
            $rid = Reseller::where('tenant_id', $lead->tenant_id)
                ->where('name', $split['reseller_name'])
                ->value('id');
            if ($rid) {
                Cache::forget("ca_reseller:{$lead->tenant_id}:" . md5($split['reseller_name'] . ':' . $rid));
                Cache::forget("referrer_perf:{$lead->tenant_id}:{$rid}");
                Cache::forget("reseller_leadids:{$lead->tenant_id}:{$rid}");
            }
        }

        return response()->json($lead->fresh(['commissionSplits']));
    }
}
