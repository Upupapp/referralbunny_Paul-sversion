<?php

namespace App\Http\Controllers;

use App\Models\DealAssignmentExtensionRequest;
use App\Models\DealExtensionRequestBatch;
use App\Models\Reseller;
use App\Services\BulkDealExtensionService;
use App\Services\DealExtensionEligibilityService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * API controller for bulk deal extension request operations.
 *
 * Referrer routes:  auth:reseller
 * Admin routes:     auth:tenant,web (admin or super admin)
 *
 * Tenant isolation enforced on every query — never trusts frontend tenant_id.
 */
class BulkDealExtensionController extends Controller
{
    public function __construct(
        private BulkDealExtensionService       $bulk,
        private DealExtensionEligibilityService $eligibility,
    ) {}

    // ── Referrer endpoints ─────────────────────────────────────────

    /**
     * GET /api/extension-requests/eligible-deals
     * Returns deals eligible for extension request for the authenticated Referrer.
     */
    public function eligibleDeals(Request $request): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId) return response()->json(['error' => 'No tenant context.'], 403);
        $reseller = $this->resolveReseller($tenantId);
        if (!$reseller) return response()->json(['error' => 'Referrer not found.'], 403);

        $results = $this->eligibility->getEligibleDealsForReseller($reseller, $tenantId);

        return response()->json($results->map(fn($row) => [
            'deal_id'    => $row['deal_id'],
            'name'       => $row['deal']->name,
            'stage'      => $row['deal']->stage,
            'status'     => $row['deal']->status,
            'days_left'  => $row['deal']->days_left,
            'eligible'   => $row['eligible'],
            'reason'     => $row['reason'],
        ])->values());
    }

    /**
     * POST /api/extension-requests/bulk
     * Referrer submits a bulk extension request.
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId) return response()->json(['error' => 'No tenant context.'], 403);
        $reseller = $this->resolveReseller($tenantId);
        if (!$reseller) return response()->json(['error' => 'Referrer not found.'], 403);

        $data = $request->validate([
            'deal_ids'                 => 'required|array|min:1',
            'deal_ids.*'               => 'required|string',
            'requested_extension_days' => 'required|integer|min:1|max:90',
            'shared_reason'            => 'required|string|min:10|max:2000',
            'per_deal_notes'           => 'nullable|array',
            'per_deal_notes.*'         => 'nullable|string|max:2000',
        ]);

        try {
            $result = $this->bulk->createBulkRequest(
                reseller:       $reseller,
                tenantId:       $tenantId,
                dealIds:        $data['deal_ids'],
                requestedDays:  (int) $data['requested_extension_days'],
                sharedReason:   $data['shared_reason'],
                perDealNotes:   $data['per_deal_notes'] ?? [],
            );

            return response()->json([
                'success'    => true,
                'message'    => "Extension request submitted for {$result['batch']->total_items} deal" . ($result['batch']->total_items > 1 ? 's' : '') . '.',
                'batch'      => [
                    'id'              => $result['batch']->id,
                    'batch_reference' => $result['batch']->batch_reference,
                    'total_items'     => $result['batch']->total_items,
                    'status'          => $result['batch']->status,
                ],
                'eligible_count'   => $result['eligible']->count(),
                'ineligible_count' => $result['ineligible']->count(),
                'ineligible'       => $result['ineligible']->map(fn($row) => [
                    'deal_id' => $row['deal_id'],
                    'name'    => $row['deal']->name,
                    'reason'  => $row['reason'],
                ])->values(),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/extension-requests/batches/{batchId}
     * Referrer or Admin fetches a batch with its items.
     */
    public function showBatch(Request $request, string $batchId): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId) return response()->json(['error' => 'No tenant context.'], 403);

        $batch = $this->bulk->getBatchWithItems($batchId, $tenantId);
        if (!$batch) return response()->json(['error' => 'Batch not found.'], 404);

        // Referrer can only view their own batches; TenantUsers must be admin/manager
        if (Auth::guard('reseller')->check()) {
            $reseller = $this->resolveReseller($tenantId);
            if (!$reseller || $batch->requested_by_reseller_id !== $reseller->id) {
                return response()->json(['error' => 'Not authorized.'], 403);
            }
        } elseif (!$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        return response()->json($this->formatBatch($batch, $tenantId));
    }

    // ── Admin/Manager endpoints ────────────────────────────────────

    /**
     * GET /api/extension-requests/batches
     * Admin lists all batches for the tenant.
     */
    public function indexBatches(Request $request): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $status  = $request->query('status');
        $search  = $request->query('search');
        $perPage = min((int) ($request->query('per_page', 20)), 100);

        $paginated = $this->bulk->getBatchesPaginated($tenantId, $status ?: null, $search ?: null, $perPage);

        return response()->json([
            'data'          => collect($paginated->items())->map(fn($b) => [
                'id'              => $b->id,
                'batch_reference' => $b->batch_reference,
                'status'          => $b->status,
                'status_label'    => $b->status_label,
                'total_items'     => $b->total_items,
                'pending_count'   => $b->pending_count,
                'approved_count'  => $b->approved_count,
                'declined_count'  => $b->declined_count,
                'skipped_count'   => $b->skipped_count,
                'shared_reason'   => \Illuminate\Support\Str::limit($b->shared_reason, 120),
                'requested_days'  => $b->requested_extension_days,
                'submitted_at'    => $b->submitted_at,
                'created_at'      => $b->created_at,
            ])->values(),
            'current_page'  => $paginated->currentPage(),
            'last_page'     => $paginated->lastPage(),
            'per_page'      => $paginated->perPage(),
            'total'         => $paginated->total(),
            'has_more'      => $paginated->hasMorePages(),
        ]);
    }

    /**
     * POST /api/extension-requests/{requestId}/approve
     * Admin approves a single item (standalone or from a batch).
     */
    public function approveItem(Request $request, string $requestId): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'approved_days' => 'required|integer|min:1|max:90',
            'reviewer_note' => 'nullable|string|max:2000',
        ]);

        try {
            [$actorId] = $this->resolveActor();
            $result = $this->bulk->approveItem(
                requestId:     $requestId,
                tenantId:      $tenantId,
                reviewerUserId:$actorId,
                approvedDays:  (int) $data['approved_days'],
                reviewerNote:  $data['reviewer_note'] ?? null,
            );

            return response()->json([
                'success' => true,
                'message' => "Extension approved. {$result->approved_days} days added.",
                'request' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/extension-requests/{requestId}/decline
     * Admin declines a single item.
     */
    public function declineItem(Request $request, string $requestId): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'reviewer_note' => 'required|string|min:5|max:2000',
        ]);

        try {
            [$actorId] = $this->resolveActor();
            $result = $this->bulk->declineItem(
                requestId:     $requestId,
                tenantId:      $tenantId,
                reviewerUserId:$actorId,
                reviewerNote:  $data['reviewer_note'],
            );

            return response()->json(['success' => true, 'message' => 'Extension declined.', 'request' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/extension-requests/{requestId}/skip
     * Admin skips a single item (remains pending for later review).
     */
    public function skipItem(Request $request, string $requestId): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'reviewer_note' => 'nullable|string|max:2000',
        ]);

        try {
            [$actorId] = $this->resolveActor();
            $result = $this->bulk->skipItem(
                requestId:     $requestId,
                tenantId:      $tenantId,
                reviewerUserId:$actorId,
                reviewerNote:  $data['reviewer_note'] ?? null,
            );

            return response()->json(['success' => true, 'message' => 'Skipped for now. Request remains pending.', 'request' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/extension-requests/batches/{batchId}/approve-all
     */
    public function approveAll(Request $request, string $batchId): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'approved_days' => 'required|integer|min:1|max:90',
            'reviewer_note' => 'nullable|string|max:2000',
        ]);

        [$actorId] = $this->resolveActor();
        $results = $this->bulk->approveAll($batchId, $tenantId, $actorId, (int) $data['approved_days'], $data['reviewer_note'] ?? null);

        return response()->json($this->batchActionResponse('approved', $results));
    }

    /**
     * POST /api/extension-requests/batches/{batchId}/decline-all
     */
    public function declineAll(Request $request, string $batchId): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'reviewer_note' => 'required|string|min:5|max:2000',
        ]);

        [$actorId] = $this->resolveActor();
        $results = $this->bulk->declineAll($batchId, $tenantId, $actorId, $data['reviewer_note']);

        return response()->json($this->batchActionResponse('declined', $results));
    }

    /**
     * POST /api/extension-requests/batches/{batchId}/skip-all
     */
    public function skipAll(Request $request, string $batchId): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'reviewer_note' => 'nullable|string|max:2000',
        ]);

        [$actorId] = $this->resolveActor();
        $results = $this->bulk->skipAll($batchId, $tenantId, $actorId, $data['reviewer_note'] ?? null);

        return response()->json($this->batchActionResponse('skipped', $results));
    }

    /**
     * POST /api/extension-requests/batches/{batchId}/approve-selected
     */
    public function approveSelected(Request $request, string $batchId): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'request_ids'   => 'required|array|min:1',
            'request_ids.*' => 'required|string',
            'approved_days' => 'required|integer|min:1|max:90',
            'reviewer_note' => 'nullable|string|max:2000',
        ]);

        [$actorId] = $this->resolveActor();
        $results = $this->bulk->approveSelected($batchId, $tenantId, $actorId, $data['request_ids'], (int) $data['approved_days'], $data['reviewer_note'] ?? null);

        return response()->json($this->batchActionResponse('approved', $results));
    }

    /**
     * POST /api/extension-requests/batches/{batchId}/decline-selected
     */
    public function declineSelected(Request $request, string $batchId): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'request_ids'   => 'required|array|min:1',
            'request_ids.*' => 'required|string',
            'reviewer_note' => 'required|string|min:5|max:2000',
        ]);

        [$actorId] = $this->resolveActor();
        $results = $this->bulk->declineSelected($batchId, $tenantId, $actorId, $data['request_ids'], $data['reviewer_note']);

        return response()->json($this->batchActionResponse('declined', $results));
    }

    /**
     * POST /api/extension-requests/batches/{batchId}/skip-selected
     */
    public function skipSelected(Request $request, string $batchId): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'request_ids'   => 'required|array|min:1',
            'request_ids.*' => 'required|string',
            'reviewer_note' => 'nullable|string|max:2000',
        ]);

        [$actorId] = $this->resolveActor();
        $results = $this->bulk->skipSelected($batchId, $tenantId, $actorId, $data['request_ids'], $data['reviewer_note'] ?? null);

        return response()->json($this->batchActionResponse('skipped', $results));
    }

    /**
     * POST /api/extension-requests/batches/{batchId}/reject-selected-approve-rest
     * Decline the selected items, approve all remaining pending items.
     */
    public function rejectSelectedApproveRest(Request $request, string $batchId): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'request_ids'     => 'required|array|min:1',
            'request_ids.*'   => 'required|string',
            'approved_days'   => 'required|integer|min:1|max:90',
            'rejection_reason'=> 'required|string|min:5|max:2000',
            'approval_note'   => 'nullable|string|max:2000',
        ]);

        [$actorId] = $this->resolveActor();
        $results = $this->bulk->rejectSelectedApproveRest(
            batchId:          $batchId,
            tenantId:         $tenantId,
            reviewerUserId:   $actorId,
            rejectRequestIds: $data['request_ids'],
            approvedDays:     (int) $data['approved_days'],
            rejectionReason:  $data['rejection_reason'],
            approvalNote:     $data['approval_note'] ?? null,
        );

        $approved = count($results['approved'] ?? []);
        $declined = count($results['declined'] ?? []);
        $failed   = count($results['failed'] ?? []);

        return response()->json([
            'success'       => ($approved + $declined) > 0,
            'message'       => "{$approved} approved, {$declined} rejected" . ($failed > 0 ? ", {$failed} failed." : '.'),
            'approved'      => $approved,
            'declined'      => $declined,
            'failed'        => $failed,
            'failed_detail' => $results['failed'] ?? [],
        ]);
    }

    /**
     * POST /api/extension-requests/batches/{batchId}/approve-selected-reject-rest
     * Approve the selected items, decline all remaining pending items.
     */
    public function approveSelectedRejectRest(Request $request, string $batchId): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'request_ids'     => 'required|array|min:1',
            'request_ids.*'   => 'required|string',
            'approved_days'   => 'required|integer|min:1|max:90',
            'rejection_reason'=> 'required|string|min:5|max:2000',
            'approval_note'   => 'nullable|string|max:2000',
        ]);

        [$actorId] = $this->resolveActor();
        $results = $this->bulk->approveSelectedRejectRest(
            batchId:           $batchId,
            tenantId:          $tenantId,
            reviewerUserId:    $actorId,
            approveRequestIds: $data['request_ids'],
            approvedDays:      (int) $data['approved_days'],
            rejectionReason:   $data['rejection_reason'],
            approvalNote:      $data['approval_note'] ?? null,
        );

        $approved = count($results['approved'] ?? []);
        $declined = count($results['declined'] ?? []);
        $failed   = count($results['failed'] ?? []);

        return response()->json([
            'success'       => ($approved + $declined) > 0,
            'message'       => "{$approved} approved, {$declined} rejected" . ($failed > 0 ? ", {$failed} failed." : '.'),
            'approved'      => $approved,
            'declined'      => $declined,
            'failed'        => $failed,
            'failed_detail' => $results['failed'] ?? [],
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────

    private function formatBatch(DealExtensionRequestBatch $batch, string $tenantId): array
    {
        return [
            'id'               => $batch->id,
            'batch_reference'  => $batch->batch_reference,
            'status'           => $batch->status,
            'status_label'     => $batch->status_label,
            'total_items'      => $batch->total_items,
            'pending_count'    => $batch->pending_count,
            'approved_count'   => $batch->approved_count,
            'declined_count'   => $batch->declined_count,
            'skipped_count'    => $batch->skipped_count,
            'shared_reason'    => $batch->shared_reason,
            'requested_days'   => $batch->requested_extension_days,
            'submitted_at'     => $batch->submitted_at,
            'resolved_at'      => $batch->resolved_at,
            'items'            => $batch->items->map(fn($item) => [
                'id'                => $item->id,
                'deal_id'           => $item->deal_id,
                'deal_name'         => $item->lead?->name,
                'deal_stage'        => $item->lead?->stage,
                'deal_status'       => $item->lead?->status,
                'days_left'         => $item->lead?->days_left,
                'reseller_name'     => $item->lead?->reseller_name,
                'current_days_left' => $item->current_days_left,
                'requested_days'    => $item->requested_days,
                'per_deal_note'     => $item->per_deal_note,
                'status'            => $item->status,
                'status_label'      => $item->status_label,
                'approved_days'     => $item->approved_days,
                'admin_note'        => $item->admin_note,
                'reviewed_at'       => $item->reviewed_at,
                'current_expiry_at' => $item->current_expiry_at,
                'approved_expiry_at'=> $item->approved_new_expiry_at,
                'action_url'        => "/tenant/{$tenantId}/deals/{$item->deal_id}",
            ])->values(),
        ];
    }

    private function batchActionResponse(string $action, array $results): array
    {
        $successCount = count($results[$action] ?? []);
        $failCount    = count($results['failed'] ?? []);
        $total        = $successCount + $failCount;

        $message = match ($action) {
            'approved' => "{$successCount} extension" . ($successCount > 1 ? 's' : '') . " approved.",
            'declined' => "{$successCount} extension request" . ($successCount > 1 ? 's' : '') . " declined.",
            'skipped'  => "{$successCount} request" . ($successCount > 1 ? 's' : '') . " skipped for now.",
            default    => "{$successCount} of {$total} requests processed.",
        };

        if ($failCount > 0) {
            $message .= " {$failCount} could not be processed (already reviewed).";
        }

        return [
            'success'       => $successCount > 0,
            'message'       => $message,
            'processed'     => $successCount,
            'failed'        => $failCount,
            'failed_detail' => $results['failed'] ?? [],
        ];
    }

    private function isAdminOrManager(): bool
    {
        if (Auth::guard('web')->check()) return true;
        $userId = Auth::guard('tenant')->id();
        if (!$userId) return false;
        $tenantId = TenantContext::id();
        if (!$tenantId) return false;
        $role = \App\Models\TenantMembership::where('tenant_user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->value('role');
        return in_array($role, ['owner', 'admin', 'manager']);
    }

    private function resolveActor(): array
    {
        if (Auth::guard('tenant')->check()) {
            return [Auth::guard('tenant')->user()->id, 'manager'];
        }
        if (Auth::guard('web')->check()) {
            return [Auth::guard('web')->user()->id, 'admin'];
        }
        if (Auth::guard('reseller')->check()) {
            return [Auth::guard('reseller')->user()->id, 'referrer'];
        }
        return ['system', 'system'];
    }

    private function resolveReseller(string $tenantId): ?Reseller
    {
        $user = Auth::guard('reseller')->user();
        if (!$user) return null;

        return Reseller::where('id', $user->id)->where('tenant_id', $tenantId)->first();
    }
}
