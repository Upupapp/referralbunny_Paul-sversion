<?php

namespace App\Http\Controllers;

use App\Models\DealApprovalRequest;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadHistory;
use App\Models\CommissionSplit;
use App\Models\Reseller;
use App\Services\CommissionCalculationService;
use App\Services\DealActivityService;
use App\Services\DealPartnerSplitService;
use App\Services\NotificationDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResellerDealController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Resolve the authenticated reseller; abort if not found. */
    private function reseller(): Reseller
    {
        $r = Auth::guard('reseller')->user();
        if ($r instanceof Reseller) return $r;
        abort(403, 'Reseller authentication required.');
    }

    /** Load deal, verify it belongs to tenant, verify reseller is assigned. */
    private function deal(string $tenantId, string $dealId): Lead
    {
        $reseller = $this->reseller();

        $lead = Lead::where('id', $dealId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$lead) abort(404, 'Deal not found.');

        // Access check: reseller must be assigned to this deal
        if (!$this->resellerCanAccessDeal($reseller, $lead)) {
            abort(403, 'You do not have access to this deal.');
        }

        return $lead;
    }

    private function resellerCanAccessDeal(Reseller $reseller, Lead $lead): bool
    {
        // Primary assignment via reseller_name
        if ($reseller->name === $lead->reseller_name) return true;

        // Secondary: commission split (additional referrer)
        return CommissionSplit::where('lead_id', $lead->id)
            ->where('reseller_name', $reseller->name)
            ->exists();
    }

    // ── Deal Detail (Show) ────────────────────────────────────────────────────

    public function show(string $tenantId, string $dealId)
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        // Pending approval requests for this deal
        $pendingApprovals = [];
        try {
            $pendingApprovals = DealApprovalRequest::where('deal_id', $dealId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->get()
                ->toArray();
        } catch (\Throwable) {}

        // Notes visible to this reseller
        $notes = [];
        try {
            $notes = LeadNote::where('lead_id', $dealId)
                ->orderBy('created_at', 'asc')
                ->get()
                ->toArray();
        } catch (\Throwable) {}

        // Commission splits
        $splits = CommissionSplit::where('lead_id', $dealId)->get();

        // Partner splits
        $partnerSplits = [];
        try {
            $partnerSplits = DB::table('deal_partner_splits')
                ->where('deal_id', $dealId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'removed')
                ->get()
                ->toArray();
        } catch (\Throwable) {}

        // Attachments
        $attachments = [];
        try {
            $attachments = DB::table('lead_attachments')
                ->where('lead_id', $dealId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
        } catch (\Throwable) {}

        // Activity (lead_history)
        $history = [];
        try {
            $history = DB::table('lead_history')
                ->where('lead_id', $dealId)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->toArray();
        } catch (\Throwable) {}

        // Financial breakdown
        $calc      = app(CommissionCalculationService::class);
        $breakdown = [];
        try {
            $breakdown = $calc->breakdownFromLead($lead);
        } catch (\Throwable) {}

        return view('reseller.deals.show', compact(
            'reseller', 'tenant', 'lead', 'tenantId',
            'pendingApprovals', 'notes', 'splits', 'partnerSplits',
            'attachments', 'history', 'breakdown'
        ));
    }

    // ── Add Note ─────────────────────────────────────────────────────────────

    public function addNote(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $data = $request->validate([
            'body' => 'required|string|max:10000',
        ]);

        $body = strip_tags(trim($data['body']));
        if (!$body) return response()->json(['error' => 'Note body is required.'], 422);

        DB::beginTransaction();
        try {
            $note = LeadNote::create([
                'lead_id' => $lead->id,
                'text'    => $body,
                'author'  => $reseller->name,
            ]);

            app(DealActivityService::class)->record($lead, 'Note added by referrer', 'note', [
                'category'   => 'note',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController addNote failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not save note. Please try again.'], 500);
        }

        return response()->json(['success' => true, 'note' => $note]);
    }

    // ── Update Deal Amount ────────────────────────────────────────────────────

    public function updateAmount(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $data = $request->validate([
            'deal_value' => 'required|numeric|min:1|max:999999999',
            'reason'     => 'required|string|max:1000',
        ]);

        $oldAmount = (float) $lead->deal_value;
        $newAmount = (float) $data['deal_value'];

        if (abs($oldAmount - $newAmount) < 0.01) {
            return response()->json(['error' => 'New amount is the same as the current amount.'], 422);
        }

        DB::beginTransaction();
        try {
            $lead->update(['deal_value' => $newAmount]);

            $calc         = app(CommissionCalculationService::class);
            $newBreakdown = $calc->breakdownFromLead($lead->fresh());

            app(DealActivityService::class)->record($lead, 'Deal amount updated by referrer', 'amount', [
                'category'   => 'financial',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'old_values' => ['deal_value' => $oldAmount],
                'new_values' => ['deal_value' => $newAmount],
            ]);

            // Notify tenant admins
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Deal amount changed',
                body:         $reseller->name . ' changed "' . $lead->name . '" from ₱' . number_format($oldAmount) . ' to ₱' . number_format($newAmount) . '.',
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'Review Deal',
                dedupeSuffix: $dealId . ':amount:' . now()->format('YmdHi'),
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController updateAmount failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not update amount. Please try again.'], 500);
        }

        return response()->json([
            'success'   => true,
            'deal_value'=> $newAmount,
            'breakdown' => $newBreakdown ?? [],
        ]);
    }

    // ── Move Stage ────────────────────────────────────────────────────────────

    public function moveStage(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $data = $request->validate([
            'stage'  => 'required|in:introduction,presentation,contract_sent,signed,paid',
            'reason' => 'nullable|string|max:1000',
        ]);

        $oldStage    = $lead->stage;
        $targetStage = $data['stage'];

        if ($oldStage === $targetStage) {
            return response()->json(['error' => 'Deal is already at this stage.'], 422);
        }

        // Check for a pending approval for this deal + stage
        $pendingExists = DealApprovalRequest::where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'deal_stage_move')
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            return response()->json(['error' => 'A stage move request is already pending approval.'], 422);
        }

        DB::beginTransaction();
        try {
            $lead->update(['stage' => $targetStage]);

            app(DealActivityService::class)->record($lead, 'Stage moved by referrer', 'stage', [
                'category'   => 'stage',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'old_values' => ['stage' => $oldStage],
                'new_values' => ['stage' => $targetStage],
            ]);

            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Deal stage moved by referrer',
                body:         $reseller->name . ' moved "' . $lead->name . '" from ' . ucfirst(str_replace('_', ' ', $oldStage)) . ' to ' . ucfirst(str_replace('_', ' ', $targetStage)) . '.',
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'View Deal',
                dedupeSuffix: $dealId . ':stage:' . $targetStage . ':' . now()->format('YmdH'),
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController moveStage failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not move stage. Please try again.'], 500);
        }

        return response()->json(['success' => true, 'stage' => $targetStage]);
    }

    // ── Request Stage Approval (when docs incomplete) ────────────────────────

    public function requestStageApproval(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $data = $request->validate([
            'target_stage' => 'required|in:introduction,presentation,contract_sent,signed,paid',
            'reason'       => 'required|string|max:2000',
        ]);

        // Block if already pending
        $alreadyPending = DealApprovalRequest::where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'deal_stage_move')
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            return response()->json(['error' => 'A stage approval request is already pending.'], 422);
        }

        DB::beginTransaction();
        try {
            $approval = DealApprovalRequest::create([
                'tenant_id'          => $tenantId,
                'type'               => 'deal_stage_move',
                'deal_id'            => $dealId,
                'requested_by_type'  => 'reseller',
                'requested_by_id'    => (string) $reseller->id,
                'status'             => 'pending',
                'reason'             => $data['reason'],
                'request_payload'    => [
                    'current_stage' => $lead->stage,
                    'target_stage'  => $data['target_stage'],
                    'deal_name'     => $lead->name,
                    'referrer_name' => $reseller->name,
                ],
            ]);

            app(DealActivityService::class)->record($lead, 'Stage move approval requested by referrer', 'approval', [
                'category'   => 'approval',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'new_values' => ['approval_id' => $approval->id, 'target_stage' => $data['target_stage']],
            ]);

            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'high',
                title:        'Stage move approval needed',
                body:         $reseller->name . ' requested to move "' . $lead->name . '" to ' . ucfirst(str_replace('_', ' ', $data['target_stage'])) . '. Review required.',
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'Review & Approve',
                dedupeSuffix: $dealId . ':stage_approval:' . $approval->id,
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController requestStageApproval failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not submit approval request. Please try again.'], 500);
        }

        return response()->json(['success' => true, 'approval_id' => $approval->id]);
    }

    // ── Request Archive ───────────────────────────────────────────────────────

    public function requestArchive(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $data = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        // Block if already pending
        $alreadyPending = DealApprovalRequest::where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'deal_archive')
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            return response()->json(['error' => 'An archive request is already pending approval.'], 422);
        }

        DB::beginTransaction();
        try {
            $approval = DealApprovalRequest::create([
                'tenant_id'         => $tenantId,
                'type'              => 'deal_archive',
                'deal_id'           => $dealId,
                'requested_by_type' => 'reseller',
                'requested_by_id'   => (string) $reseller->id,
                'status'            => 'pending',
                'reason'            => $data['reason'],
                'request_payload'   => [
                    'deal_name'     => $lead->name,
                    'deal_stage'    => $lead->stage,
                    'referrer_name' => $reseller->name,
                ],
            ]);

            app(DealActivityService::class)->record($lead, 'Archive request submitted by referrer', 'archive', [
                'category'   => 'archive',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'new_values' => ['approval_id' => $approval->id, 'reason' => $data['reason']],
            ]);

            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'high',
                title:        'Deal archive approval needed',
                body:         $reseller->name . ' requested to archive "' . $lead->name . '". Reason: ' . $data['reason'],
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'Review & Decide',
                dedupeSuffix: $dealId . ':archive:' . $approval->id,
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController requestArchive failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not submit archive request. Please try again.'], 500);
        }

        return response()->json(['success' => true, 'approval_id' => $approval->id]);
    }

    // ── Add Partner Split ────────────────────────────────────────────────────

    public function addPartnerSplit(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $data = $request->validate([
            'partner_name'       => 'required|string|max:150',
            'partner_email'      => 'required|email|max:200',
            'split_share_value'  => 'required|numeric|min:0.01',
            'split_share_type'   => 'required|in:percentage,fixed_amount',
        ]);

        try {
            app(DealPartnerSplitService::class)->createOrUpdate(
                tenantId:        $tenantId,
                dealId:          $dealId,
                partnerName:     $data['partner_name'],
                partnerEmail:    $data['partner_email'],
                splitValue:      $data['split_share_value'],
                splitType:       $data['split_share_type'],
                actorId:         (string) $reseller->id,
                actorType:       'reseller',
            );

            app(DealActivityService::class)->record($lead, 'Partner split added by referrer', 'partner', [
                'category'   => 'partner',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'new_values' => ['partner' => $data['partner_name'], 'split' => $data['split_share_value'], 'type' => $data['split_share_type']],
            ]);

            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Partner added to deal',
                body:         $reseller->name . ' added ' . $data['partner_name'] . ' as a Partner to "' . $lead->name . '".',
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'Review Deal',
                dedupeSuffix: $dealId . ':partner:' . md5($data['partner_email']),
            );
        } catch (\Throwable $e) {
            Log::error('ResellerDealController addPartnerSplit failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not add partner. Please try again.'], 500);
        }

        return response()->json(['success' => true]);
    }

    // ── Add Co-Referrer ───────────────────────────────────────────────────────

    public function addReferrer(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $data = $request->validate([
            'reseller_name' => 'required|string|max:150',
            'percentage'    => 'required|numeric|min:0|max:100',
        ]);

        // Prevent adding yourself as a duplicate
        if (strtolower($data['reseller_name']) === strtolower($reseller->name)) {
            return response()->json(['error' => 'You are already assigned to this deal.'], 422);
        }

        // Prevent cross-tenant assignment (verify reseller exists in this tenant)
        $targetReseller = Reseller::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [strtolower($data['reseller_name'])])
            ->first();

        // Check not already a split
        $alreadySplit = CommissionSplit::where('lead_id', $dealId)
            ->whereRaw('LOWER(reseller_name) = ?', [strtolower($data['reseller_name'])])
            ->exists();

        if ($alreadySplit) {
            return response()->json(['error' => 'This referrer is already associated with this deal.'], 422);
        }

        DB::beginTransaction();
        try {
            CommissionSplit::create([
                'lead_id'         => $dealId,
                'reseller_name'   => $data['reseller_name'],
                'percentage'      => $data['percentage'],
                'role'            => 'secondary',
                'activity_status' => 'active',
            ]);

            app(DealActivityService::class)->record($lead, 'Additional referrer added by referrer', 'referrer', [
                'category'   => 'assignment',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'new_values' => ['added_referrer' => $data['reseller_name'], 'percentage' => $data['percentage']],
            ]);

            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Additional referrer added',
                body:         $reseller->name . ' added ' . $data['reseller_name'] . ' as a referrer on "' . $lead->name . '".',
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'Review Deal',
                dedupeSuffix: $dealId . ':coreferrer:' . md5(strtolower($data['reseller_name'])),
            );

            // Notify the added referrer if they have an active account
            if ($targetReseller && in_array($targetReseller->status, ['active', 'nda_signed'])) {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   (string) $targetReseller->id,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'high',
                    title:        'You were added to a deal',
                    body:         'You were added as a referrer on "' . $lead->name . '".',
                    actionUrl:    url("/reseller/{$tenantId}/deals/{$dealId}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: $dealId . ':added_referrer:' . (string) $targetReseller->id,
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController addReferrer failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not add referrer. Please try again.'], 500);
        }

        return response()->json(['success' => true]);
    }

    // ── Approve / Reject deal approval (for tenant admins) ───────────────────

    public function approveRequest(Request $request, string $tenantId, string $approvalId): JsonResponse
    {
        // Tenant admin/manager only
        if (!Auth::guard('tenant')->check() && !Auth::guard('web')->check()) {
            abort(403);
        }

        $data = $request->validate(['reviewer_note' => 'nullable|string|max:1000']);

        $approval = DealApprovalRequest::where('id', $approvalId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->firstOrFail();

        $lead = Lead::find($approval->deal_id);

        DB::beginTransaction();
        try {
            $approval->update([
                'status'       => 'approved',
                'reviewer_note'=> $data['reviewer_note'] ?? null,
                'approved_at'  => now(),
            ]);

            // Execute the approved action
            if ($approval->type === 'deal_stage_move' && $lead) {
                $targetStage = $approval->request_payload['target_stage'] ?? null;
                if ($targetStage) {
                    $oldStage = $lead->stage;
                    $lead->update(['stage' => $targetStage]);

                    app(DealActivityService::class)->record($lead, 'Stage move approved by admin', 'stage', [
                        'category'   => 'stage',
                        'actor_name' => Auth::guard('tenant')->user()?->name ?? 'Admin',
                        'actor_role' => 'admin',
                        'old_values' => ['stage' => $oldStage],
                        'new_values' => ['stage' => $targetStage],
                    ]);
                }
            } elseif ($approval->type === 'deal_archive' && $lead) {
                $lead->update(['status' => 'expired']); // use expired as archive proxy until archived_at column added

                app(DealActivityService::class)->record($lead, 'Deal archive approved', 'archive', [
                    'category'   => 'archive',
                    'actor_name' => Auth::guard('tenant')->user()?->name ?? 'Admin',
                    'actor_role' => 'admin',
                ]);
            }

            // Notify the requesting reseller
            $referrerId = $approval->requested_by_id;
            if ($approval->requested_by_type === 'reseller') {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   $referrerId,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'normal',
                    title:        'Your request was approved',
                    body:         'Your ' . str_replace('_', ' ', $approval->type) . ' request for "' . ($lead?->name ?? 'a deal') . '" was approved.',
                    actionUrl:    url("/reseller/{$tenantId}/deals/" . $approval->deal_id),
                    actionLabel:  'View Deal',
                    dedupeSuffix: $approvalId . ':approved',
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController approveRequest failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not process approval. Please try again.'], 500);
        }

        return response()->json(['success' => true]);
    }

    public function rejectRequest(Request $request, string $tenantId, string $approvalId): JsonResponse
    {
        if (!Auth::guard('tenant')->check() && !Auth::guard('web')->check()) {
            abort(403);
        }

        $data = $request->validate(['reviewer_note' => 'required|string|max:1000']);

        $approval = DealApprovalRequest::where('id', $approvalId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->firstOrFail();

        $lead = Lead::find($approval->deal_id);

        DB::beginTransaction();
        try {
            $approval->update([
                'status'        => 'rejected',
                'reviewer_note' => $data['reviewer_note'],
                'rejected_at'   => now(),
            ]);

            if ($lead) {
                app(DealActivityService::class)->record($lead, 'Request rejected by admin', 'approval', [
                    'category'   => 'approval',
                    'actor_name' => Auth::guard('tenant')->user()?->name ?? 'Admin',
                    'actor_role' => 'admin',
                    'new_values' => ['reason' => $data['reviewer_note']],
                ]);
            }

            if ($approval->requested_by_type === 'reseller') {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   $approval->requested_by_id,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'normal',
                    title:        'Your request was not approved',
                    body:         'Your request for "' . ($lead?->name ?? 'a deal') . '" was not approved. Reason: ' . $data['reviewer_note'],
                    actionUrl:    url("/reseller/{$tenantId}/deals/" . $approval->deal_id),
                    actionLabel:  'View Deal',
                    dedupeSuffix: $approvalId . ':rejected',
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Could not process rejection. Please try again.'], 500);
        }

        return response()->json(['success' => true]);
    }
}
