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
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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

        // Derive tenant from authenticated context, never from user input
        $tenantId = TenantContext::id();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif (!TenantContext::isSuperAdmin()) {
            abort(403, 'Tenant context required.');
        }

        if ($request->filled('reseller_name')) {
            $query->where('reseller_name', $request->reseller_name);
        }

        $leads = $query->get();

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

        return response()->json($leads);
    }

    public function store(Request $request): JsonResponse
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

        $baseCost    = (float) ($data['base_cost']    ?? 0);
        $addedAmount = (float) ($data['added_amount'] ?? 0);
        $dealValue   = $baseCost + $addedAmount ?: (float) ($data['deal_value'] ?? 0);

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

        LeadHistory::create([
            'lead_id' => $lead->id,
            'action'  => 'Lead created',
            'type'    => 'assignment',
            'date'    => now()->toDateString(),
        ]);

        // Fire event → triggers deal created emails (reseller + tenant admin)
        DealCreatedEvent::dispatch(
            leadId:        $lead->id,
            leadName:      $lead->name,
            tenantId:      $lead->tenant_id,
            resellerName:  $lead->reseller_name,
            stage:         $lead->stage,
            dealValue:     (float) ($lead->deal_value ?? 0),
            daysLeft:      $lead->days_left ?? 21,
        );

        // Auto-create reseller + send invite if a new email was provided
        $resellerCreated = false;
        $inviteSent      = false;

        if (!empty($data['new_reseller_email'])) {
            $exists = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($data) {
                    $q->where('name', $data['reseller_name'])
                      ->orWhere('email', $data['new_reseller_email']);
                })
                ->exists();

            if (!$exists) {
                $setupToken = Str::random(64);
                DB::table('resellers')->insert([
                    'id'                => (string) Str::uuid(),
                    'tenant_id'         => $tenantId,
                    'name'              => $data['reseller_name'],
                    'email'             => strtolower(trim($data['new_reseller_email'])),
                    'status'            => 'invited',
                    'setup_token'       => $setupToken,
                    'assigned_leads'    => 1,
                    'closed_value'      => 0,
                    'performance_score' => 0,
                    'is_anonymous'      => false,
                    'joined_date'       => now()->toDateString(),
                    'created_at'        => now(),
                ]);
                $resellerCreated = true;

                $tenantName = DB::table('tenants')->where('id', $tenantId)->value('name') ?? 'Referral Bunny';
                $setupUrl   = url('/reseller/setup?token=' . $setupToken);

                try {
                    Mail::send(new ResellerInvitation(
                        resellerName:  $data['reseller_name'],
                        resellerEmail: strtolower(trim($data['new_reseller_email'])),
                        tenantName:    $tenantName,
                        setupUrl:      $setupUrl,
                        dealName:      $data['name'],
                    ));
                    $inviteSent = true;
                } catch (\Throwable $e) {
                    Log::warning("Reseller invite email failed for {$data['new_reseller_email']}: {$e->getMessage()}");
                }
            }
        }

        return response()->json(array_merge(
            $lead->load(['commissionSplits', 'notes', 'history'])->toArray(),
            ['reseller_created' => $resellerCreated, 'invite_sent' => $inviteSent]
        ), 201);
    }

    public function show(Lead $lead): JsonResponse
    {
        $lead->assertBelongsToCurrentTenant();
        return response()->json($lead->load(['commissionSplits', 'notes', 'history', 'attachments', 'links']));
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $lead->assertBelongsToCurrentTenant();

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

        // Auto-recompute deal_value whenever financial fields change
        if (isset($data['base_cost']) || isset($data['added_amount'])) {
            $data['deal_value'] = ((float)($data['base_cost'] ?? $lead->base_cost))
                                + ((float)($data['added_amount'] ?? $lead->added_amount));
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
        $stages = ['introduction', 'presentation', 'contract_sent', 'signed', 'paid'];
        $currentIndex = array_search($lead->stage, $stages);

        // Accept explicit target stage OR default to next-in-sequence
        if ($request->filled('stage') && in_array($request->stage, $stages)) {
            $targetStage = $request->stage;
        } else {
            if ($currentIndex === false || $currentIndex >= count($stages) - 1) {
                return response()->json(['message' => 'Already at final stage.'], 422);
            }
            $targetStage = $stages[$currentIndex + 1];
        }

        $isLocking = $targetStage === 'signed';
        $isPaid    = $targetStage === 'paid';

        $lead->update([
            'stage'             => $targetStage,
            'commission_status' => $isLocking ? 'locked' : ($isPaid ? 'paid' : $lead->commission_status),
        ]);

        LeadHistory::create([
            'lead_id' => $lead->id,
            'action'  => 'Stage moved to ' . str_replace('_', ' ', $targetStage),
            'type'    => 'stage',
            'date'    => now()->toDateString(),
        ]);

        if ($isLocking) {
            LeadHistory::create([
                'lead_id' => $lead->id,
                'action'  => 'Contract signed — commission locked at ₱' . number_format((float)$lead->added_amount * 0.70, 2),
                'type'    => 'commission',
                'date'    => now()->toDateString(),
            ]);
        }

        if ($isPaid) {
            LeadHistory::create([
                'lead_id' => $lead->id,
                'action'  => 'Payment received — commission pool ₱' . number_format((float)$lead->added_amount * 0.70, 2) . ' marked paid',
                'type'    => 'commission',
                'date'    => now()->toDateString(),
            ]);
        }

        return response()->json($lead->fresh(['commissionSplits', 'history']));
    }

    public function addNote(Request $request, Lead $lead): JsonResponse
    {
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

        LeadHistory::create([
            'lead_id' => $lead->id,
            'action'  => 'Reassigned to ' . $data['reseller_name'],
            'type'    => 'assignment',
            'reseller'=> $data['reseller_name'],
            'date'    => now()->toDateString(),
        ]);

        return response()->json($lead->fresh(['commissionSplits', 'history']));
    }

    public function updateCommissionSplits(Request $request, Lead $lead): JsonResponse
    {
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
