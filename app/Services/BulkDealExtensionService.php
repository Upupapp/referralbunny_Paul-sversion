<?php

namespace App\Services;

use App\Events\DealExtensionApproved;
use App\Models\ActivityLog;
use App\Models\DealAssignmentExtensionRequest;
use App\Models\DealExtensionRequestBatch;
use App\Models\Lead;
use App\Models\Reseller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manages bulk deal extension request batches.
 *
 * Extends (does NOT replace) DealAssignmentExtensionService.
 * Single-deal extension requests remain unchanged and use their own service.
 * This service creates batches and per-item records linked via batch_id.
 *
 * LGU IDS stage limits are untouched — extension adds days to days_left only.
 */
class BulkDealExtensionService
{
    public function __construct(
        private DealExtensionEligibilityService $eligibility,
        private NotificationDispatchService     $notifications,
        private CriticalActionService           $criticalActions,
    ) {}

    // ── Batch creation ────────────────────────────────────────────

    /**
     * Create a bulk extension request batch.
     *
     * Returns ['batch' => DealExtensionRequestBatch, 'eligible' => [...], 'skipped' => [...]]
     *
     * @throws \InvalidArgumentException
     */
    public function createBulkRequest(
        Reseller $reseller,
        string   $tenantId,
        array    $dealIds,
        int      $requestedDays,
        string   $sharedReason,
        array    $perDealNotes = []
    ): array {
        if ($reseller->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Tenant mismatch. You cannot request extensions for this tenant.');
        }

        if (empty($dealIds)) {
            throw new \InvalidArgumentException('At least one deal must be selected.');
        }

        if ($requestedDays < 1 || $requestedDays > 90) {
            throw new \InvalidArgumentException('Requested extension must be between 1 and 90 days.');
        }

        if (empty(trim($sharedReason)) || strlen(trim($sharedReason)) < 10) {
            throw new \InvalidArgumentException('A reason of at least 10 characters is required.');
        }

        // Load requested deals (only current tenant, not archived)
        $deals = Lead::where('tenant_id', $tenantId)
            ->whereIn('id', $dealIds)
            ->whereNull('deleted_at')
            ->get();

        // Run eligibility on fetched deals
        $eligibilityResults = $this->eligibility->checkManyForReseller($deals, $reseller);
        $eligible   = $eligibilityResults->where('eligible', true);
        $ineligible = $eligibilityResults->where('eligible', false);

        if ($eligible->isEmpty()) {
            throw new \InvalidArgumentException('None of the selected deals are eligible for an extension request.');
        }

        return DB::transaction(function () use (
            $reseller, $tenantId, $requestedDays, $sharedReason,
            $perDealNotes, $eligible, $ineligible
        ) {
            // Create the batch
            $batch = DealExtensionRequestBatch::create([
                'tenant_id'                => $tenantId,
                'requested_by_reseller_id' => $reseller->id,
                'requested_by_role'        => 'referrer',
                'shared_reason'            => trim($sharedReason),
                'requested_extension_days' => $requestedDays,
                'status'                   => 'pending',
                'total_items'              => $eligible->count(),
                'pending_count'            => $eligible->count(),
                'approved_count'           => 0,
                'declined_count'           => 0,
                'skipped_count'            => 0,
                'submitted_at'             => now(),
            ]);

            // Create one DealAssignmentExtensionRequest per eligible deal
            foreach ($eligible as $row) {
                /** @var Lead $deal */
                $deal = $row['deal'];

                $currentDaysLeft   = $deal->days_left ?? 0;
                $currentExpiryAt   = now()->addDays(max(0, $currentDaysLeft));
                $requestedExpiryAt = $currentExpiryAt->copy()->addDays($requestedDays);
                $perNote           = $perDealNotes[$deal->id] ?? null;

                $request = DealAssignmentExtensionRequest::create([
                    'tenant_id'               => $tenantId,
                    'batch_id'                => $batch->id,
                    'deal_id'                 => $deal->id,
                    'requested_by_user_id'    => $reseller->id,
                    'requested_by_role'       => 'referrer',
                    'current_stage'           => $deal->stage,
                    'current_expiry_at'       => $currentExpiryAt,
                    'current_days_left'       => $currentDaysLeft,
                    'requested_days'          => $requestedDays,
                    'requested_new_expiry_at' => $requestedExpiryAt,
                    'reason'                  => trim($sharedReason),
                    'per_deal_note'           => $perNote ? trim($perNote) : null,
                    'status'                  => 'pending_review',
                ]);

                $this->auditDeal($tenantId, $deal->id, $request->id, 'deal_extension_requested', $reseller->id, [
                    'batch_id'         => $batch->id,
                    'batch_reference'  => $batch->batch_reference,
                    'requested_days'   => $requestedDays,
                    'current_days_left'=> $currentDaysLeft,
                    'bulk'             => true,
                ]);
            }

            // Notify admins/managers
            $this->notifyAdminsBulkRequest($tenantId, $reseller, $batch);

            // Bust critical actions cache
            $this->criticalActions->invalidateCache($tenantId);

            return [
                'batch'      => $batch->fresh(),
                'eligible'   => $eligible->values(),
                'ineligible' => $ineligible->values(),
            ];
        });
    }

    // ── Per-item decisions ─────────────────────────────────────────

    /**
     * Approve a single extension request item (from a batch or standalone via this service).
     */
    public function approveItem(
        string  $requestId,
        string  $tenantId,
        string  $reviewerUserId,
        int     $approvedDays,
        ?string $reviewerNote = null
    ): DealAssignmentExtensionRequest {
        return DB::transaction(function () use ($requestId, $tenantId, $reviewerUserId, $approvedDays, $reviewerNote) {
            $request = $this->loadForReview($requestId, $tenantId);
            $deal    = Lead::where('id', $request->deal_id)->where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail();

            if ($approvedDays < 1 || $approvedDays > 90) {
                throw new \InvalidArgumentException('Approved days must be between 1 and 90.');
            }

            $newDaysLeft      = max(0, ($deal->days_left ?? 0)) + $approvedDays;
            $approvedExpiryAt = now()->addDays($newDaysLeft);

            DB::table('leads')->where('id', $deal->id)->update([
                'days_left'  => $newDaysLeft,
                'status'     => 'active',
                'updated_at' => now(),
            ]);

            $request->update([
                'status'                 => 'approved',
                'approved_days'          => $approvedDays,
                'approved_new_expiry_at' => $approvedExpiryAt,
                'admin_note'             => $reviewerNote,
                'reviewed_by_user_id'    => $reviewerUserId,
                'reviewed_at'            => now(),
            ]);

            if ($request->batch_id) {
                $this->recalculateBatchStatus($request->batch_id, $tenantId);
            }

            $this->notifyResellerOfDecision($tenantId, $deal, $request, 'approved');

            $this->auditDeal($tenantId, $deal->id, $request->id, 'deal_extension_approved', $reviewerUserId, [
                'approved_days'   => $approvedDays,
                'new_days_left'   => $newDaysLeft,
                'reviewer_note'   => $reviewerNote,
                'batch_id'        => $request->batch_id,
            ]);

            DealExtensionApproved::dispatch(
                leadId:       $deal->id,
                leadName:     $deal->name,
                tenantId:     $tenantId,
                resellerName: $deal->reseller_name ?? '',
                approvedDays: $approvedDays,
                newDaysLeft:  $newDaysLeft,
                adminNote:    $reviewerNote,
            );

            $this->criticalActions->invalidateCache($tenantId);

            return $request->fresh();
        });
    }

    /**
     * Decline a single extension request item.
     */
    public function declineItem(
        string  $requestId,
        string  $tenantId,
        string  $reviewerUserId,
        string  $reviewerNote
    ): DealAssignmentExtensionRequest {
        if (empty(trim($reviewerNote))) {
            throw new \InvalidArgumentException('A reason is required when declining an extension request.');
        }

        return DB::transaction(function () use ($requestId, $tenantId, $reviewerUserId, $reviewerNote) {
            $request = $this->loadForReview($requestId, $tenantId);
            $deal    = Lead::where('id', $request->deal_id)->where('tenant_id', $tenantId)->firstOrFail();

            $request->update([
                'status'              => 'rejected',
                'admin_note'          => $reviewerNote,
                'reviewed_by_user_id' => $reviewerUserId,
                'reviewed_at'         => now(),
            ]);

            if ($request->batch_id) {
                $this->recalculateBatchStatus($request->batch_id, $tenantId);
            }

            $this->notifyResellerOfDecision($tenantId, $deal, $request, 'rejected');

            $this->auditDeal($tenantId, $deal->id, $request->id, 'deal_extension_rejected', $reviewerUserId, [
                'reviewer_note' => $reviewerNote,
                'batch_id'      => $request->batch_id,
            ]);

            $this->criticalActions->invalidateCache($tenantId);

            return $request->fresh();
        });
    }

    /**
     * Skip a single extension request item (keep pending, visible for later review).
     */
    public function skipItem(
        string  $requestId,
        string  $tenantId,
        string  $reviewerUserId,
        ?string $reviewerNote = null
    ): DealAssignmentExtensionRequest {
        return DB::transaction(function () use ($requestId, $tenantId, $reviewerUserId, $reviewerNote) {
            $request = $this->loadForReview($requestId, $tenantId);

            $request->update([
                'status'              => 'skipped',
                'admin_note'          => $reviewerNote,
                'reviewed_by_user_id' => $reviewerUserId,
                'reviewed_at'         => now(),
            ]);

            if ($request->batch_id) {
                $this->recalculateBatchStatus($request->batch_id, $tenantId);
            }

            // Do NOT notify the referrer as approved or declined when skipped
            $this->auditDeal($tenantId, $request->deal_id, $request->id, 'deal_extension_skipped', $reviewerUserId, [
                'reviewer_note' => $reviewerNote,
                'batch_id'      => $request->batch_id,
            ]);

            $this->criticalActions->invalidateCache($tenantId);

            return $request->fresh();
        });
    }

    // ── Batch-level decisions ──────────────────────────────────────

    /**
     * Approve all pending items in a batch.
     */
    public function approveAll(
        string  $batchId,
        string  $tenantId,
        string  $reviewerUserId,
        int     $approvedDays,
        ?string $reviewerNote = null
    ): array {
        $batch = $this->loadBatch($batchId, $tenantId);

        $pendingItems = DealAssignmentExtensionRequest::where('batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending_review', 'skipped'])
            ->get();

        $results = ['approved' => [], 'failed' => []];

        foreach ($pendingItems as $item) {
            try {
                $approved          = $this->approveItem($item->id, $tenantId, $reviewerUserId, $approvedDays, $reviewerNote);
                $results['approved'][] = $approved;
            } catch (\Throwable $e) {
                $results['failed'][] = ['request_id' => $item->id, 'reason' => $e->getMessage()];
            }
        }

        $freshBatch = $batch->fresh();
        // Only send partial-result notification if the batch is not yet fully resolved.
        // When fully resolved, notifyResellerBatchComplete already fired from recalculateBatchStatus.
        if ($freshBatch && ($freshBatch->pending_count + $freshBatch->skipped_count) > 0) {
            $this->notifyResellerBatchPartialResult($tenantId, $freshBatch, $results);
        }
        $this->criticalActions->invalidateCache($tenantId);

        return $results;
    }

    /**
     * Decline all pending items in a batch.
     */
    public function declineAll(
        string  $batchId,
        string  $tenantId,
        string  $reviewerUserId,
        string  $reviewerNote
    ): array {
        $batch = $this->loadBatch($batchId, $tenantId);

        $pendingItems = DealAssignmentExtensionRequest::where('batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending_review', 'skipped'])
            ->get();

        $results = ['declined' => [], 'failed' => []];

        foreach ($pendingItems as $item) {
            try {
                $declined            = $this->declineItem($item->id, $tenantId, $reviewerUserId, $reviewerNote);
                $results['declined'][] = $declined;
            } catch (\Throwable $e) {
                $results['failed'][] = ['request_id' => $item->id, 'reason' => $e->getMessage()];
            }
        }

        $freshBatch = $batch->fresh();
        if ($freshBatch && ($freshBatch->pending_count + $freshBatch->skipped_count) > 0) {
            $this->notifyResellerBatchPartialResult($tenantId, $freshBatch, $results);
        }
        $this->criticalActions->invalidateCache($tenantId);

        return $results;
    }

    /**
     * Skip all pending items in a batch.
     */
    public function skipAll(
        string  $batchId,
        string  $tenantId,
        string  $reviewerUserId,
        ?string $reviewerNote = null
    ): array {
        $this->loadBatch($batchId, $tenantId);

        $pendingItems = DealAssignmentExtensionRequest::where('batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending_review')
            ->get();

        $results = ['skipped' => [], 'failed' => []];

        foreach ($pendingItems as $item) {
            try {
                $skipped           = $this->skipItem($item->id, $tenantId, $reviewerUserId, $reviewerNote);
                $results['skipped'][] = $skipped;
            } catch (\Throwable $e) {
                $results['failed'][] = ['request_id' => $item->id, 'reason' => $e->getMessage()];
            }
        }

        $this->criticalActions->invalidateCache($tenantId);

        return $results;
    }

    /**
     * Approve selected items in a batch.
     */
    public function approveSelected(
        string  $batchId,
        string  $tenantId,
        string  $reviewerUserId,
        array   $requestIds,
        int     $approvedDays,
        ?string $reviewerNote = null
    ): array {
        $this->loadBatch($batchId, $tenantId);

        $results = ['approved' => [], 'failed' => []];

        foreach ($requestIds as $requestId) {
            try {
                $approved          = $this->approveItem($requestId, $tenantId, $reviewerUserId, $approvedDays, $reviewerNote);
                $results['approved'][] = $approved;
            } catch (\Throwable $e) {
                $results['failed'][] = ['request_id' => $requestId, 'reason' => $e->getMessage()];
            }
        }

        $this->criticalActions->invalidateCache($tenantId);

        return $results;
    }

    /**
     * Decline selected items in a batch.
     */
    public function declineSelected(
        string  $batchId,
        string  $tenantId,
        string  $reviewerUserId,
        array   $requestIds,
        string  $reviewerNote
    ): array {
        $this->loadBatch($batchId, $tenantId);

        $results = ['declined' => [], 'failed' => []];

        foreach ($requestIds as $requestId) {
            try {
                $declined            = $this->declineItem($requestId, $tenantId, $reviewerUserId, $reviewerNote);
                $results['declined'][] = $declined;
            } catch (\Throwable $e) {
                $results['failed'][] = ['request_id' => $requestId, 'reason' => $e->getMessage()];
            }
        }

        $this->criticalActions->invalidateCache($tenantId);

        return $results;
    }

    /**
     * Skip selected items in a batch.
     */
    public function skipSelected(
        string  $batchId,
        string  $tenantId,
        string  $reviewerUserId,
        array   $requestIds,
        ?string $reviewerNote = null
    ): array {
        $this->loadBatch($batchId, $tenantId);

        $results = ['skipped' => [], 'failed' => []];

        foreach ($requestIds as $requestId) {
            try {
                $skipped           = $this->skipItem($requestId, $tenantId, $reviewerUserId, $reviewerNote);
                $results['skipped'][] = $skipped;
            } catch (\Throwable $e) {
                $results['failed'][] = ['request_id' => $requestId, 'reason' => $e->getMessage()];
            }
        }

        $this->criticalActions->invalidateCache($tenantId);

        return $results;
    }

    // ── Batch status recalculation ────────────────────────────────

    public function recalculateBatchStatus(string $batchId, string $tenantId): void
    {
        $counts = DB::table('deal_assignment_extension_requests')
            ->where('batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved'       THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected'       THEN 1 ELSE 0 END) as declined,
                SUM(CASE WHEN status = 'skipped'        THEN 1 ELSE 0 END) as skipped
            ")
            ->first();

        if (!$counts) return;

        $pending  = (int) $counts->pending;
        $approved = (int) $counts->approved;
        $declined = (int) $counts->declined;
        $skipped  = (int) $counts->skipped;
        $total    = (int) $counts->total;

        $actionableRemaining = $pending + $skipped;

        $status = match (true) {
            $actionableRemaining === 0 && $approved > 0 && $declined === 0 => 'approved',
            $actionableRemaining === 0 && $declined > 0 && $approved === 0 => 'declined',
            $actionableRemaining === 0 && $approved > 0 && $declined > 0   => 'partially_approved',
            $actionableRemaining > 0   && $approved > 0                    => 'partially_approved',
            $actionableRemaining > 0   && $declined > 0 && $approved === 0 => 'partially_declined',
            default                                                         => 'pending',
        };

        $justResolved = ($actionableRemaining === 0);
        $resolvedAt   = $justResolved ? now() : null;

        // Only fire completion notification if the batch was previously unresolved
        $wasUnresolved = DealExtensionRequestBatch::where('id', $batchId)
            ->whereNull('resolved_at')
            ->exists();

        DealExtensionRequestBatch::where('id', $batchId)->update([
            'total_items'       => $total,
            'pending_count'     => $pending,
            'approved_count'    => $approved,
            'declined_count'    => $declined,
            'skipped_count'     => $skipped,
            'status'            => $status,
            'resolved_at'       => $resolvedAt,
            'last_decision_at'  => now(),
            'updated_at'        => now(),
        ]);

        // Notify referrer once when batch becomes fully resolved (per-item review path)
        if ($justResolved && $wasUnresolved) {
            $batch = DealExtensionRequestBatch::where('id', $batchId)->first();
            if ($batch?->requested_by_reseller_id) {
                $this->notifyResellerBatchComplete($tenantId, $batch, $approved, $declined);
            }
        }
    }

    // ── Queries ───────────────────────────────────────────────────

    public function getBatchesForTenant(string $tenantId, ?string $status = null): Collection
    {
        $q = DealExtensionRequestBatch::where('tenant_id', $tenantId)
            ->with('requestedByReseller:id,name,tenant_id')
            ->orderByDesc('created_at');

        if ($status) {
            $q->where('status', $status);
        }

        return $q->get();
    }

    public function getBatchesForReseller(string $tenantId, string $resellerId): Collection
    {
        return DealExtensionRequestBatch::where('tenant_id', $tenantId)
            ->where('requested_by_reseller_id', $resellerId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getBatchWithItems(string $batchId, string $tenantId): ?DealExtensionRequestBatch
    {
        return DealExtensionRequestBatch::where('id', $batchId)
            ->where('tenant_id', $tenantId)
            ->with(['items.lead:id,name,stage,status,days_left,reseller_name'])
            ->first();
    }

    // ── Notifications ──────────────────────────────────────────────

    private function notifyAdminsBulkRequest(string $tenantId, Reseller $reseller, DealExtensionRequestBatch $batch): void
    {
        try {
            $count = $batch->total_items;
            $this->notifications->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'high',
                title:        "Bulk extension request submitted",
                body:         "{$reseller->name} requested extensions for {$count} deal" . ($count > 1 ? 's' : '') . ". Reason: " . \Illuminate\Support\Str::limit($batch->shared_reason, 80),
                actionUrl:    url("/tenant/{$tenantId}/extension-requests/{$batch->id}"),
                actionLabel:  'Review Extension Request',
                dedupeSuffix: "bulk_ext_req:{$batch->id}",
                metadata:     ['batch_id' => $batch->id, 'reseller_id' => $reseller->id, 'deal_count' => $count],
            );
        } catch (\Throwable) {}
    }

    private function notifyResellerOfDecision(string $tenantId, Lead $deal, DealAssignmentExtensionRequest $request, string $decision): void
    {
        try {
            $reseller = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->where('id', $request->requested_by_user_id)
                ->first();

            if (!$reseller) return;

            $messages = [
                'approved' => "Your extension request for \"{$deal->name}\" was approved. {$request->approved_days} days added.",
                'rejected' => "Your extension request for \"{$deal->name}\" was declined. Reason: {$request->admin_note}",
            ];

            $message = $messages[$decision] ?? "Your extension request for \"{$deal->name}\" was updated.";

            $this->notifications->dispatchToReseller(
                resellerId:   $reseller->id,
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     $decision === 'approved' ? 'normal' : 'high',
                title:        $decision === 'approved' ? "Extension approved for \"{$deal->name}\"" : "Extension declined for \"{$deal->name}\"",
                body:         $message,
                actionUrl:    url("/reseller/{$tenantId}/deals/{$deal->id}"),
                actionLabel:  'View Deal',
                dedupeSuffix: "ext_decision:{$request->id}:{$decision}",
                metadata:     ['extension_request_id' => $request->id, 'deal_id' => $deal->id, 'batch_id' => $request->batch_id],
            );
        } catch (\Throwable) {}
    }

    private function notifyResellerBatchPartialResult(string $tenantId, DealExtensionRequestBatch $batch, array $results): void
    {
        try {
            if (!$batch->requested_by_reseller_id) return;

            $approvedCount = count($results['approved'] ?? []);
            $declinedCount = count($results['declined'] ?? []);
            if ($approvedCount === 0 && $declinedCount === 0) return;

            $parts = [];
            if ($approvedCount > 0) $parts[] = "{$approvedCount} approved";
            if ($declinedCount > 0) $parts[] = "{$declinedCount} declined";
            $summary = implode(', ', $parts);

            $this->notifications->dispatchToReseller(
                resellerId:   $batch->requested_by_reseller_id,
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        "Bulk extension request partially reviewed",
                body:         "Your bulk extension request was reviewed: {$summary}.",
                actionUrl:    url("/reseller/{$tenantId}/extension-requests/{$batch->id}"),
                actionLabel:  'View My Request',
                dedupeSuffix: "bulk_ext_result:{$batch->id}:{$approvedCount}:{$declinedCount}",
                metadata:     ['batch_id' => $batch->id, 'approved' => $approvedCount, 'declined' => $declinedCount],
            );
        } catch (\Throwable) {}
    }

    private function notifyResellerBatchComplete(string $tenantId, DealExtensionRequestBatch $batch, int $approved, int $declined): void
    {
        try {
            $parts = [];
            if ($approved > 0) $parts[] = "{$approved} approved";
            if ($declined > 0) $parts[] = "{$declined} declined";
            $summary = implode(', ', $parts) ?: 'all reviewed';

            $this->notifications->dispatchToReseller(
                resellerId:   $batch->requested_by_reseller_id,
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        "Your extension request has been fully reviewed",
                body:         "Your bulk extension request ({$batch->batch_reference}) has been reviewed: {$summary}.",
                actionUrl:    url("/reseller/{$tenantId}/extension-requests/{$batch->id}"),
                actionLabel:  'View Results',
                dedupeSuffix: "bulk_ext_complete:{$batch->id}",
                metadata:     ['batch_id' => $batch->id, 'approved' => $approved, 'declined' => $declined],
            );
        } catch (\Throwable) {}
    }

    // ── Private helpers ───────────────────────────────────────────

    private function loadBatch(string $batchId, string $tenantId): DealExtensionRequestBatch
    {
        $batch = DealExtensionRequestBatch::where('id', $batchId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$batch) {
            throw new \InvalidArgumentException('Extension request batch not found.');
        }

        return $batch;
    }

    private function loadForReview(string $requestId, string $tenantId): DealAssignmentExtensionRequest
    {
        $request = DealAssignmentExtensionRequest::where('id', $requestId)
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->first();

        if (!$request) {
            throw new \InvalidArgumentException('Extension request not found.');
        }

        if (!in_array($request->status, ['pending_review', 'skipped'])) {
            throw new \InvalidArgumentException("Extension request is already {$request->status}.");
        }

        return $request;
    }

    private function auditDeal(string $tenantId, string $dealId, string $requestId, string $event, string $actorId, array $extra = []): void
    {
        try {
            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id'   => $actorId,
                'action'    => $event,
                'entity'    => 'extension_request',
                'entity_id' => $requestId,
                'metadata'  => json_encode(array_merge([
                    'deal_id'              => $dealId,
                    'extension_request_id' => $requestId,
                    'timestamp'            => now()->toIso8601String(),
                ], $extra)),
            ]);
        } catch (\Throwable) {}
    }
}
