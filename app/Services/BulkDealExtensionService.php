<?php

namespace App\Services;

use App\Events\DealExtensionApproved;
use App\Mail\BulkExtensionAdminNotifyMail;
use App\Mail\BulkExtensionDecisionMail;
use App\Models\ActivityLog;
use App\Models\DealAssignmentExtensionRequest;
use App\Models\DealExtensionRequestBatch;
use App\Models\Lead;
use App\Models\Reseller;
use App\Services\EmailLogger;
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

        // Audit items collected inside the transaction but written outside.
        // auditDeal calls ActivityLog::create() with user_id=bigint; a reseller
        // id is a UUID which causes a type error caught by auditDeal's catch block.
        // PHP swallows the exception but PostgreSQL marks the whole transaction as
        // aborted (SQLSTATE[25P02]), killing every subsequent statement in the batch.
        // Running audits after commit keeps the core transaction clean.
        $auditItems  = [];
        $createdBatch = null;

        $result = DB::transaction(function () use (
            $reseller, $tenantId, $requestedDays, $sharedReason,
            $perDealNotes, $eligible, $ineligible, &$auditItems, &$createdBatch
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

                // Queue audit instead of calling inside the transaction
                $auditItems[] = [$tenantId, $deal->id, $request->id, 'deal_extension_requested', $reseller->id, [
                    'batch_id'          => $batch->id,
                    'batch_reference'   => $batch->batch_reference,
                    'requested_days'    => $requestedDays,
                    'current_days_left' => $currentDaysLeft,
                    'bulk'              => true,
                ]];
            }

            $createdBatch = $batch;

            return [
                'batch'      => $batch->fresh(),
                'eligible'   => $eligible->values(),
                'ineligible' => $ineligible->values(),
            ];
        });

        // Write audit entries after the transaction commits (safe from type errors)
        foreach ($auditItems as $item) {
            $this->auditDeal(...$item);
        }

        // Post-commit side effects — outside the transaction so they cannot abort the batch creation
        if ($createdBatch) {
            try { $this->notifyAdminsBulkRequest($tenantId, $reseller, $createdBatch); } catch (\Throwable) {}
            try { $this->criticalActions->invalidateCache($tenantId, (string) $reseller->id); } catch (\Throwable) {}
            \Illuminate\Support\Facades\Cache::deleteMultiple(["bulk_ext_metrics:{$tenantId}", "nav_ext_req_badge:{$tenantId}"]);
        }

        return $result;
    }

    // ── Per-item decisions ─────────────────────────────────────────

    /**
     * Approve a single extension request item (from a batch or standalone via this service).
     */
    public function approveItem(
        string  $requestId,
        string  $tenantId,
        ?string $reviewerUserId,
        int     $approvedDays,
        ?string $reviewerNote = null
    ): DealAssignmentExtensionRequest {
        if ($approvedDays < 1 || $approvedDays > 90) {
            throw new \InvalidArgumentException('Approved days must be between 1 and 90.');
        }

        // Variables populated inside the closure so side effects can use them after commit.
        $deal               = null;
        $newDaysLeft        = 0;
        $completeNotifyData = null;

        $request = DB::transaction(function () use (
            $requestId, $tenantId, $reviewerUserId, $approvedDays, $reviewerNote,
            &$deal, &$newDaysLeft, &$completeNotifyData
        ) {
            $request = $this->loadForReview($requestId, $tenantId);
            $deal    = Lead::where('id', $request->deal_id)->where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail();

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
                $completeNotifyData = $this->recalculateBatchStatus($request->batch_id, $tenantId);
            }

            return $request->fresh();
        });

        // All side effects run after the transaction commits.
        // Notifications and audit calls contain DB inserts wrapped in try/catch. If any of
        // those inserts fail, PHP swallows the exception but PostgreSQL marks the transaction
        // as aborted (SQLSTATE[25P02]). Any subsequent unguarded DB statement — including the
        // DealExtensionApproved::dispatch() jobs insert — then surfaces the 25P02 error.
        // Running everything here keeps the transaction clean and prevents silent rollbacks.
        if ($completeNotifyData) {
            $this->notifyResellerBatchComplete(
                $tenantId,
                $completeNotifyData['batch'],
                $completeNotifyData['approved'],
                $completeNotifyData['declined'],
            );
        }
        $this->notifyResellerOfDecision($tenantId, $deal, $request, 'approved');
        $this->auditDeal($tenantId, $deal->id, $request->id, 'deal_extension_approved', $reviewerUserId, [
            'approved_days' => $approvedDays,
            'new_days_left' => $newDaysLeft,
            'reviewer_note' => $reviewerNote,
            'batch_id'      => $request->batch_id,
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
        $this->criticalActions->invalidateCache($tenantId, $reviewerUserId);

        return $request;
    }

    /**
     * Decline a single extension request item.
     */
    public function declineItem(
        string  $requestId,
        string  $tenantId,
        ?string $reviewerUserId,
        string  $reviewerNote
    ): DealAssignmentExtensionRequest {
        if (empty(trim($reviewerNote))) {
            throw new \InvalidArgumentException('A reason is required when declining an extension request.');
        }

        $deal               = null;
        $completeNotifyData = null;

        $request = DB::transaction(function () use (
            $requestId, $tenantId, $reviewerUserId, $reviewerNote,
            &$deal, &$completeNotifyData
        ) {
            $request = $this->loadForReview($requestId, $tenantId);
            $deal    = Lead::where('id', $request->deal_id)->where('tenant_id', $tenantId)->firstOrFail();

            $request->update([
                'status'              => 'rejected',
                'admin_note'          => $reviewerNote,
                'reviewed_by_user_id' => $reviewerUserId,
                'reviewed_at'         => now(),
            ]);

            if ($request->batch_id) {
                $completeNotifyData = $this->recalculateBatchStatus($request->batch_id, $tenantId);
            }

            return $request->fresh();
        });

        // Side effects after transaction commits — same 25P02 guard as approveItem.
        if ($completeNotifyData) {
            $this->notifyResellerBatchComplete(
                $tenantId,
                $completeNotifyData['batch'],
                $completeNotifyData['approved'],
                $completeNotifyData['declined'],
            );
        }
        $this->notifyResellerOfDecision($tenantId, $deal, $request, 'rejected');
        $this->auditDeal($tenantId, $deal->id, $request->id, 'deal_extension_rejected', $reviewerUserId, [
            'reviewer_note' => $reviewerNote,
            'batch_id'      => $request->batch_id,
        ]);
        $this->criticalActions->invalidateCache($tenantId, $reviewerUserId);

        return $request;
    }

    /**
     * Skip a single extension request item (keep pending, visible for later review).
     */
    public function skipItem(
        string  $requestId,
        string  $tenantId,
        ?string $reviewerUserId,
        ?string $reviewerNote = null
    ): DealAssignmentExtensionRequest {
        $completeNotifyData = null;

        $request = DB::transaction(function () use (
            $requestId, $tenantId, $reviewerUserId, $reviewerNote,
            &$completeNotifyData
        ) {
            $request = $this->loadForReview($requestId, $tenantId);

            $request->update([
                'status'              => 'skipped',
                'admin_note'          => $reviewerNote,
                'reviewed_by_user_id' => $reviewerUserId,
                'reviewed_at'         => now(),
            ]);

            if ($request->batch_id) {
                $completeNotifyData = $this->recalculateBatchStatus($request->batch_id, $tenantId);
            }

            return $request->fresh();
        });

        // Side effects after transaction commits — same 25P02 guard as approveItem.
        if ($completeNotifyData) {
            $this->notifyResellerBatchComplete(
                $tenantId,
                $completeNotifyData['batch'],
                $completeNotifyData['approved'],
                $completeNotifyData['declined'],
            );
        }
        // Do NOT notify the referrer as approved or declined when skipped
        $this->auditDeal($tenantId, $request->deal_id, $request->id, 'deal_extension_skipped', $reviewerUserId, [
            'reviewer_note' => $reviewerNote,
            'batch_id'      => $request->batch_id,
        ]);
        $this->criticalActions->invalidateCache($tenantId, $reviewerUserId);

        return $request;
    }

    // ── Batch-level decisions ──────────────────────────────────────

    /**
     * Approve all pending items in a batch.
     */
    public function approveAll(
        string  $batchId,
        string  $tenantId,
        ?string $reviewerUserId,
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

        return $results;
    }

    /**
     * Decline all pending items in a batch.
     */
    public function declineAll(
        string  $batchId,
        string  $tenantId,
        ?string $reviewerUserId,
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

        return $results;
    }

    /**
     * Skip all pending items in a batch.
     */
    public function skipAll(
        string  $batchId,
        string  $tenantId,
        ?string $reviewerUserId,
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

        return $results;
    }

    /**
     * Approve selected items in a batch.
     */
    public function approveSelected(
        string  $batchId,
        string  $tenantId,
        ?string $reviewerUserId,
        array   $requestIds,
        int     $approvedDays,
        ?string $reviewerNote = null
    ): array {
        $this->loadBatch($batchId, $tenantId);

        // Scope to IDs that actually belong to this batch to prevent cross-batch manipulation
        $requestIds = DealAssignmentExtensionRequest::where('batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $requestIds)
            ->pluck('id')
            ->all();

        $results = ['approved' => [], 'failed' => []];

        foreach ($requestIds as $requestId) {
            try {
                $approved          = $this->approveItem($requestId, $tenantId, $reviewerUserId, $approvedDays, $reviewerNote);
                $results['approved'][] = $approved;
            } catch (\Throwable $e) {
                $results['failed'][] = ['request_id' => $requestId, 'reason' => $e->getMessage()];
            }
        }

        $this->criticalActions->invalidateCache($tenantId, $reviewerUserId);

        return $results;
    }

    /**
     * Decline selected items in a batch.
     */
    public function declineSelected(
        string  $batchId,
        string  $tenantId,
        ?string $reviewerUserId,
        array   $requestIds,
        string  $reviewerNote
    ): array {
        $this->loadBatch($batchId, $tenantId);

        $requestIds = DealAssignmentExtensionRequest::where('batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $requestIds)
            ->pluck('id')
            ->all();

        $results = ['declined' => [], 'failed' => []];

        foreach ($requestIds as $requestId) {
            try {
                $declined            = $this->declineItem($requestId, $tenantId, $reviewerUserId, $reviewerNote);
                $results['declined'][] = $declined;
            } catch (\Throwable $e) {
                $results['failed'][] = ['request_id' => $requestId, 'reason' => $e->getMessage()];
            }
        }

        $this->criticalActions->invalidateCache($tenantId, $reviewerUserId);

        return $results;
    }

    /**
     * Skip selected items in a batch.
     */
    public function skipSelected(
        string  $batchId,
        string  $tenantId,
        ?string $reviewerUserId,
        array   $requestIds,
        ?string $reviewerNote = null
    ): array {
        $this->loadBatch($batchId, $tenantId);

        $requestIds = DealAssignmentExtensionRequest::where('batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $requestIds)
            ->pluck('id')
            ->all();

        $results = ['skipped' => [], 'failed' => []];

        foreach ($requestIds as $requestId) {
            try {
                $skipped           = $this->skipItem($requestId, $tenantId, $reviewerUserId, $reviewerNote);
                $results['skipped'][] = $skipped;
            } catch (\Throwable $e) {
                $results['failed'][] = ['request_id' => $requestId, 'reason' => $e->getMessage()];
            }
        }

        $this->criticalActions->invalidateCache($tenantId, $reviewerUserId);

        return $results;
    }

    /**
     * Reject the selected items and approve all remaining eligible items in the batch.
     * Atomic: both decline and approve loops run, failures are captured per-item.
     */
    public function rejectSelectedApproveRest(
        string  $batchId,
        string  $tenantId,
        ?string $reviewerUserId,
        array   $rejectRequestIds,
        int     $approvedDays,
        string  $rejectionReason,
        ?string $approvalNote = null
    ): array {
        $this->loadBatch($batchId, $tenantId);

        $allPendingIds = DealAssignmentExtensionRequest::where('batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending_review', 'skipped'])
            ->pluck('id')
            ->all();

        $rejectIds  = array_values(array_intersect($rejectRequestIds, $allPendingIds));
        $approveIds = array_values(array_diff($allPendingIds, $rejectIds));

        $results = ['approved' => [], 'declined' => [], 'failed' => []];

        foreach ($rejectIds as $id) {
            try {
                $results['declined'][] = $this->declineItem($id, $tenantId, $reviewerUserId, $rejectionReason);
            } catch (\Throwable $e) {
                $results['failed'][] = ['request_id' => $id, 'reason' => $e->getMessage()];
            }
        }

        foreach ($approveIds as $id) {
            try {
                $results['approved'][] = $this->approveItem($id, $tenantId, $reviewerUserId, $approvedDays, $approvalNote);
            } catch (\Throwable $e) {
                $results['failed'][] = ['request_id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $this->criticalActions->invalidateCache($tenantId, $reviewerUserId);

        return $results;
    }

    /**
     * Approve the selected items and reject all remaining eligible items in the batch.
     */
    public function approveSelectedRejectRest(
        string  $batchId,
        string  $tenantId,
        ?string $reviewerUserId,
        array   $approveRequestIds,
        int     $approvedDays,
        string  $rejectionReason,
        ?string $approvalNote = null
    ): array {
        $this->loadBatch($batchId, $tenantId);

        $allPendingIds = DealAssignmentExtensionRequest::where('batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending_review', 'skipped'])
            ->pluck('id')
            ->all();

        $approveIds = array_values(array_intersect($approveRequestIds, $allPendingIds));
        $rejectIds  = array_values(array_diff($allPendingIds, $approveIds));

        $results = ['approved' => [], 'declined' => [], 'failed' => []];

        foreach ($approveIds as $id) {
            try {
                $results['approved'][] = $this->approveItem($id, $tenantId, $reviewerUserId, $approvedDays, $approvalNote);
            } catch (\Throwable $e) {
                $results['failed'][] = ['request_id' => $id, 'reason' => $e->getMessage()];
            }
        }

        foreach ($rejectIds as $id) {
            try {
                $results['declined'][] = $this->declineItem($id, $tenantId, $reviewerUserId, $rejectionReason);
            } catch (\Throwable $e) {
                $results['failed'][] = ['request_id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $this->criticalActions->invalidateCache($tenantId, $reviewerUserId);

        return $results;
    }

    // ── Batch status recalculation ────────────────────────────────

    /**
     * Recalculate and persist batch status counts.
     *
     * Returns ['batch' => ..., 'approved' => int, 'declined' => int] when the batch
     * just became fully resolved and the referrer should be notified — otherwise null.
     * Callers MUST fire notifyResellerBatchComplete() with the returned data AFTER their
     * enclosing DB::transaction() commits. Calling notifications inside a transaction risks
     * silent PostgreSQL aborts (SQLSTATE[25P02]) on subsequent unguarded statements.
     */
    public function recalculateBatchStatus(string $batchId, string $tenantId): ?array
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

        if (!$counts) return null;

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

        \Illuminate\Support\Facades\Cache::forget("bulk_ext_metrics:{$tenantId}");
        \Illuminate\Support\Facades\Cache::forget("nav_ext_req_badge:{$tenantId}");

        if ($justResolved && $wasUnresolved) {
            $batch = DealExtensionRequestBatch::where('id', $batchId)->first();
            if ($batch?->requested_by_reseller_id) {
                return ['batch' => $batch, 'approved' => $approved, 'declined' => $declined];
            }
        }

        return null;
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

    public function getBatchesPaginated(
        string  $tenantId,
        ?string $status = null,
        ?string $search = null,
        int     $perPage = 20,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $q = DealExtensionRequestBatch::where('tenant_id', $tenantId)
            ->with('requestedByReseller:id,name,tenant_id')
            ->orderByDesc('created_at');

        if ($status) {
            $q->where('status', $status);
        }

        if ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('batch_reference', 'ilike', '%' . $search . '%')
                    ->orWhere('shared_reason', 'ilike', '%' . $search . '%');
            });
        }

        return $q->paginate($perPage);
    }

    public function getMetricsForTenant(string $tenantId): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "bulk_ext_metrics:{$tenantId}",
            60,
            function () use ($tenantId) {
                $batchCounts = DB::table('deal_extension_request_batches')
                    ->selectRaw("
                        COUNT(*) FILTER (WHERE status = 'pending')             AS pending_batches,
                        COUNT(*) FILTER (WHERE status = 'partially_approved')  AS partially_approved_batches,
                        COUNT(*) FILTER (WHERE status = 'approved')            AS approved_batches,
                        COUNT(*) FILTER (WHERE status = 'declined')            AS declined_batches,
                        COUNT(*) FILTER (WHERE status = 'partially_declined')  AS partially_declined_batches
                    ")
                    ->where('tenant_id', $tenantId)
                    ->first();

                $dealCounts = DB::table('deal_assignment_extension_requests')
                    ->selectRaw("
                        COUNT(*) FILTER (WHERE status = 'pending_review') AS pending_deals,
                        COUNT(*) FILTER (WHERE status = 'approved')       AS approved_deals,
                        COUNT(*) FILTER (WHERE status = 'rejected')       AS declined_deals
                    ")
                    ->where('tenant_id', $tenantId)
                    ->first();

                return [
                    'pending_batches'            => (int) ($batchCounts->pending_batches ?? 0),
                    'partially_approved_batches' => (int) ($batchCounts->partially_approved_batches ?? 0),
                    'approved_batches'           => (int) ($batchCounts->approved_batches ?? 0),
                    'declined_batches'           => (int) ($batchCounts->declined_batches ?? 0),
                    'partially_declined_batches' => (int) ($batchCounts->partially_declined_batches ?? 0),
                    'total_pending_deals'        => (int) ($dealCounts->pending_deals ?? 0),
                    'total_approved_deals'       => (int) ($dealCounts->approved_deals ?? 0),
                    'total_declined_deals'       => (int) ($dealCounts->declined_deals ?? 0),
                ];
            }
        );
    }

    public function getPendingBatchCount(string $tenantId): int
    {
        return (int) DB::table('deal_extension_request_batches')
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->count();
    }

    // ── Notifications ──────────────────────────────────────────────

    private function notifyAdminsBulkRequest(string $tenantId, Reseller $reseller, DealExtensionRequestBatch $batch): void
    {
        try {
            $count = $batch->total_items;
            $reviewUrl = url("/tenant/{$tenantId}/extension-requests/{$batch->id}");

            $this->notifications->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'high',
                title:        "Bulk extension request submitted",
                body:         "{$reseller->name} requested extensions for {$count} deal" . ($count > 1 ? 's' : '') . ". Reason: " . \Illuminate\Support\Str::limit($batch->shared_reason, 80),
                actionUrl:    $reviewUrl,
                actionLabel:  'Review Extension Request',
                dedupeSuffix: "bulk_ext_req:{$batch->id}",
                metadata:     ['batch_id' => $batch->id, 'reseller_id' => $reseller->id, 'deal_count' => $count],
            );

            // Email each admin/manager once per batch submission
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            $admins = DB::table('tenant_memberships as tm')
                ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
                ->where('tm.tenant_id', $tenantId)
                ->whereIn('tm.role', ['owner', 'admin', 'manager'])
                ->where('tm.status', 'active')
                ->select('u.email', 'u.first_name', 'u.last_name')
                ->get();

            foreach ($admins as $admin) {
                $emailKey = "bulk_ext.admin_notify.{$batch->id}.{$admin->email}";
                EmailLogger::send(
                    mailable: new BulkExtensionAdminNotifyMail(
                        adminName:      trim("{$admin->first_name} {$admin->last_name}"),
                        tenantName:     $tenant?->name ?? $tenantId,
                        referrerName:   $reseller->name,
                        dealCount:      $count,
                        requestedDays:  $batch->requested_extension_days,
                        reasonPreview:  \Illuminate\Support\Str::limit($batch->shared_reason, 120),
                        batchReference: $batch->batch_reference,
                        reviewUrl:      $reviewUrl,
                    ),
                    recipientEmail: $admin->email,
                    recipientType:  'tenant_admin',
                    emailKey:       $emailKey,
                    subject:        "Bulk extension request needs review: {$count} deal" . ($count !== 1 ? 's' : '') . " — " . ($tenant?->name ?? $tenantId),
                    tenantId:       $tenantId,
                );
            }
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
                dedupeSuffix: "bulk_ext_result_partial:{$batch->id}",
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
            $summary    = implode(', ', $parts) ?: 'all reviewed';
            $requestUrl = url("/reseller/{$tenantId}/extension-requests/{$batch->id}");

            $this->notifications->dispatchToReseller(
                resellerId:   $batch->requested_by_reseller_id,
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        "Your extension request has been fully reviewed",
                body:         "Your bulk extension request ({$batch->batch_reference}) has been reviewed: {$summary}.",
                actionUrl:    $requestUrl,
                actionLabel:  'View Results',
                dedupeSuffix: "bulk_ext_complete:{$batch->id}",
                metadata:     ['batch_id' => $batch->id, 'approved' => $approved, 'declined' => $declined],
            );

            // Send decision email to the referrer (one email per batch, not per deal)
            $reseller = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->where('id', $batch->requested_by_reseller_id)
                ->first();

            if ($reseller && !empty($reseller->email)) {
                $tenant = DB::table('tenants')->where('id', $tenantId)->first();

                // Collect top-10 deal summaries for the email body
                $items = DB::table('deal_assignment_extension_requests as r')
                    ->leftJoin('leads as l', 'r.deal_id', '=', 'l.id')
                    ->where('r.batch_id', $batch->id)
                    ->where('r.tenant_id', $tenantId)
                    ->select('l.name as deal_name', 'r.status', 'r.approved_days')
                    ->orderByRaw("CASE r.status WHEN 'approved' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END")
                    ->limit(10)
                    ->get();

                $dealSummaries = $items->map(fn($i) => [
                    'name'          => $i->deal_name ?? 'Unknown Deal',
                    'status'        => $i->status,
                    'approved_days' => $i->approved_days,
                ])->toArray();

                $emailKey = "bulk_ext.decision.{$batch->id}.{$reseller->email}";
                EmailLogger::send(
                    mailable: new BulkExtensionDecisionMail(
                        referrerName:   $reseller->name,
                        tenantName:     $tenant?->name ?? $tenantId,
                        batchReference: $batch->batch_reference,
                        approvedCount:  $approved,
                        declinedCount:  $declined,
                        skippedCount:   (int) $batch->skipped_count,
                        totalCount:     (int) $batch->total_items,
                        overallStatus:  $batch->status,
                        adminNote:      null,
                        requestUrl:     $requestUrl,
                        dealSummaries:  $dealSummaries,
                    ),
                    recipientEmail: $reseller->email,
                    recipientType:  'reseller',
                    emailKey:       $emailKey,
                    subject:        $approved > 0 && $declined === 0
                        ? "Your bulk extension request was approved — {$batch->batch_reference}"
                        : "Bulk extension request reviewed — {$approved} approved, {$declined} rejected",
                    tenantId:       $tenantId,
                );
            }
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

    private function auditDeal(string $tenantId, string $dealId, string $requestId, string $event, ?string $actorId, array $extra = []): void
    {
        try {
            // activity_logs.user_id is bigint (FK to users); reseller IDs are UUIDs.
            // Store UUID actor IDs in metadata only to avoid a type error on insert.
            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id'   => is_numeric($actorId) ? (int) $actorId : null,
                'action'    => $event,
                'entity'    => 'extension_request',
                'entity_id' => $requestId,
                'metadata'  => array_merge([
                    'deal_id'              => $dealId,
                    'extension_request_id' => $requestId,
                    'actor_id'             => $actorId,
                    'timestamp'            => now()->toIso8601String(),
                ], $extra),
            ]);
        } catch (\Throwable) {}
    }
}
