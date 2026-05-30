<?php

namespace App\Http\Controllers;

use App\Models\ExportRequest;
use App\Models\Reseller;
use App\Models\TenantConfig;
use App\Models\TenantMembership;
use App\Services\ExportApprovalService;
use App\Services\ExportPermissionService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExportController extends Controller
{
    public function __construct(
        private ExportApprovalService  $approvalService,
        private ExportPermissionService $permissionService,
    ) {}

    // ── Auth resolution helpers ───────────────────────────────────────────────

    /**
     * Resolve tenant ID exclusively from TenantContext (set by SetApiTenantContext middleware).
     * Never trusts user-supplied input directly — SA tenant-switching is handled by the middleware.
     */
    private function resolveTenantId(Request $request): ?string
    {
        // Partners may not use exports
        if (Auth::guard('partner')->check()) {
            abort(403, 'Partners may not access exports.');
        }

        return TenantContext::id();
    }

    /**
     * Resolve requester identity from whichever guard is authenticated.
     * Returns [type, id, role] or null when unauthenticated.
     */
    private function resolveRequester(string $tenantId): ?array
    {
        if (Auth::guard('web')->check()) {
            return [
                'type' => 'super_admin',
                'id'   => (string) Auth::guard('web')->id(),
                'role' => 'super_admin',
            ];
        }

        if (Auth::guard('tenant')->check()) {
            $userId     = (string) Auth::guard('tenant')->id();
            $membership = TenantMembership::where('tenant_user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();

            return [
                'type' => 'tenant_user',
                'id'   => $userId,
                'role' => $membership?->role ?? 'member',
            ];
        }

        if (Auth::guard('reseller')->check()) {
            return [
                'type' => 'reseller',
                'id'   => (string) Auth::guard('reseller')->id(),
                'role' => 'referrer',
            ];
        }

        return null;
    }

    /**
     * Return whether the current requester is an admin/owner for the given tenant.
     */
    private function requesterIsAdmin(array $requester, string $tenantId): bool
    {
        if ($requester['type'] === 'super_admin') {
            return true;
        }

        if ($requester['type'] === 'tenant_user') {
            $membership = TenantMembership::where('tenant_user_id', $requester['id'])
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();

            return $membership && in_array($membership->role, ['owner', 'admin'], true);
        }

        return false;
    }

    // ── API methods ───────────────────────────────────────────────────────────

    /**
     * GET /api/exports
     * List export requests for the authenticated user's tenant with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant context required.'], 422);
        }

        $requester = $this->resolveRequester($tenantId);
        if (!$requester) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $query = ExportRequest::forTenant($tenantId)
            ->orderByDesc('created_at');

        // Non-admins only see their own requests
        if (!$this->requesterIsAdmin($requester, $tenantId)) {
            $query->where('requester_type', $requester['type'])
                  ->where('requester_id', $requester['id']);
        }

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('type')) {
            $query->where('export_type', $request->query('type'));
        }

        if ($request->filled('role')) {
            $query->where('requester_role', $request->query('role'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $perPage    = 25;
        $page       = max(1, (int) $request->query('page', 1));
        $total      = $query->count();
        $items      = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        // KPI counts
        $pendingCount  = ExportRequest::forTenant($tenantId)->where('status', ExportRequest::STATUS_PENDING)->count();
        $readyCount    = ExportRequest::forTenant($tenantId)->ready()->count();
        $approvedThisMonth = ExportRequest::forTenant($tenantId)
            ->where('status', ExportRequest::STATUS_APPROVED)
            ->whereMonth('approved_at', now()->month)
            ->whereYear('approved_at', now()->year)
            ->count();

        return response()->json([
            'data'                => $items,
            'meta' => [
                'total'              => $total,
                'per_page'           => $perPage,
                'current_page'       => $page,
                'last_page'          => (int) ceil($total / $perPage),
                'pending_count'      => $pendingCount,
                'ready_count'        => $readyCount,
                'approved_this_month'=> $approvedThisMonth,
            ],
        ]);
    }

    /**
     * POST /api/exports
     * Create an export request.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant context required.'], 422);
        }

        $requester = $this->resolveRequester($tenantId);
        if (!$requester) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'export_type' => ['required', Rule::in(ExportRequest::EXPORT_TYPES)],
            'format'      => ['required', Rule::in(['csv', 'xlsx'])],
            'scope'       => ['nullable', 'array'],
            'fields'      => ['nullable', 'array'],
            'reason'      => ['nullable', 'string', 'max:1000'],
        ]);

        $settings   = $this->permissionService->getSettings($tenantId);
        $exportType = $validated['export_type'];
        $isSensitive = $this->permissionService->isSensitive($exportType);

        // Permission check based on requester type
        if ($requester['type'] === 'tenant_user') {
            $membership = TenantMembership::where('tenant_user_id', $requester['id'])
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();

            if (!$membership) {
                return response()->json(['message' => 'No active membership found for this tenant.'], 403);
            }

            $permission = $this->permissionService->checkTenantUserExport($membership, $exportType, $settings);
        } elseif ($requester['type'] === 'reseller') {
            $reseller = Reseller::find($requester['id']);
            if (!$reseller) {
                return response()->json(['message' => 'Reseller record not found.'], 403);
            }
            $permission = $this->permissionService->checkReferrerExport($reseller, $exportType, $settings);
        } elseif ($requester['type'] === 'super_admin') {
            $permission = ['allowed' => true, 'requires_approval' => false, 'reason' => ''];
        } else {
            $permission = $this->permissionService->checkPartnerExport();
        }

        if (!$permission['allowed']) {
            return response()->json(['message' => $permission['reason']], 403);
        }

        // Check if reason is required
        if ($requester['type'] === 'reseller' && ($settings['require_reason_referrer'] ?? true)) {
            if (empty($validated['reason'])) {
                return response()->json(['message' => 'A reason is required for your export request.'], 422);
            }
        }

        if ($requester['type'] === 'tenant_user' && $requester['role'] === 'manager' && ($settings['require_reason_manager'] ?? true)) {
            if (empty($validated['reason'])) {
                return response()->json(['message' => 'A reason is required for your export request.'], 422);
            }
        }

        try {
            $exportRequest = $this->approvalService->createRequest(
                tenantId:         $tenantId,
                requesterType:    $requester['type'],
                requesterId:      $requester['id'],
                requesterRole:    $requester['role'],
                exportType:       $exportType,
                format:           $validated['format'],
                scope:            $validated['scope'] ?? [],
                fields:           $validated['fields'] ?? [],
                reason:           $validated['reason'] ?? null,
                requiresApproval: $permission['requires_approval'],
                isSensitive:      $isSensitive,
            );
        } catch (\Throwable $e) {
            Log::error('ExportController::store failed', [
                'tenant_id' => $tenantId,
                'requester' => $requester,
                'error'     => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Failed to create export request.'], 500);
        }

        return response()->json($exportRequest, 201);
    }

    /**
     * GET /api/exports/{id}
     * Show a single export request, tenant-scoped.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant context required.'], 422);
        }

        $requester = $this->resolveRequester($tenantId);
        if (!$requester) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $exportRequest = ExportRequest::forTenant($tenantId)->where('id', $id)->first();

        if (!$exportRequest) {
            return response()->json(['message' => 'Export request not found.'], 404);
        }

        // Non-admins may only view their own requests
        if (!$this->requesterIsAdmin($requester, $tenantId)) {
            if ($exportRequest->requester_type !== $requester['type']
                || (string) $exportRequest->requester_id !== $requester['id']) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        return response()->json($exportRequest);
    }

    /**
     * POST /api/exports/{id}/approve
     * Approve a pending export request (admin only).
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant context required.'], 422);
        }

        $requester = $this->resolveRequester($tenantId);
        if (!$requester) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$this->requesterIsAdmin($requester, $tenantId)) {
            return response()->json(['message' => 'Only tenant admins may approve export requests.'], 403);
        }

        $exportRequest = ExportRequest::forTenant($tenantId)->where('id', $id)->first();

        if (!$exportRequest) {
            return response()->json(['message' => 'Export request not found.'], 404);
        }

        if (!$exportRequest->isPending()) {
            return response()->json(['message' => 'Only pending export requests can be approved.'], 422);
        }

        try {
            $updated = $this->approvalService->approve($exportRequest, $requester['id']);
        } catch (\Throwable $e) {
            Log::error('ExportController::approve failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Failed to approve export request.'], 500);
        }

        return response()->json($updated);
    }

    /**
     * POST /api/exports/{id}/reject
     * Reject a pending export request (admin only).
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant context required.'], 422);
        }

        $requester = $this->resolveRequester($tenantId);
        if (!$requester) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$this->requesterIsAdmin($requester, $tenantId)) {
            return response()->json(['message' => 'Only tenant admins may reject export requests.'], 403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $exportRequest = ExportRequest::forTenant($tenantId)->where('id', $id)->first();

        if (!$exportRequest) {
            return response()->json(['message' => 'Export request not found.'], 404);
        }

        if (!$exportRequest->isPending()) {
            return response()->json(['message' => 'Only pending export requests can be rejected.'], 422);
        }

        try {
            $updated = $this->approvalService->reject($exportRequest, $requester['id'], $validated['reason']);
        } catch (\Throwable $e) {
            Log::error('ExportController::reject failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Failed to reject export request.'], 500);
        }

        return response()->json($updated);
    }

    /**
     * POST /api/exports/{id}/cancel
     * Cancel the requester's own export request.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant context required.'], 422);
        }

        $requester = $this->resolveRequester($tenantId);
        if (!$requester) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $exportRequest = ExportRequest::forTenant($tenantId)->where('id', $id)->first();

        if (!$exportRequest) {
            return response()->json(['message' => 'Export request not found.'], 404);
        }

        if (!$this->permissionService->canCancel($exportRequest, $requester['type'], $requester['id'])) {
            return response()->json(['message' => 'This export request cannot be cancelled.'], 422);
        }

        try {
            $updated = $this->approvalService->cancel($exportRequest);
        } catch (\Throwable $e) {
            Log::error('ExportController::cancel failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Failed to cancel export request.'], 500);
        }

        return response()->json($updated);
    }

    /**
     * GET /api/exports/{id}/download
     * Download the export file for a ready request.
     */
    public function download(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant context required.'], 422);
        }

        $requester = $this->resolveRequester($tenantId);
        if (!$requester) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $exportRequest = ExportRequest::forTenant($tenantId)->where('id', $id)->first();

        if (!$exportRequest) {
            return response()->json(['message' => 'Export request not found.'], 404);
        }

        if (!$this->permissionService->canDownload($exportRequest, $requester['type'], $requester['id'])) {
            // Admins may also download any ready file in their tenant
            if (!$this->requesterIsAdmin($requester, $tenantId) || !$exportRequest->canBeDownloaded()) {
                return response()->json(['message' => 'This export file is not available for download.'], 403);
            }
        }

        $filePath = $exportRequest->file_path;
        $fileName = $exportRequest->file_name ?? 'export.' . ($exportRequest->export_format ?? 'csv');

        if (!$filePath || !Storage::disk('local')->exists($filePath)) {
            return response()->json(['message' => 'Export file not found on storage.'], 404);
        }

        try {
            $this->approvalService->recordDownload($exportRequest);
        } catch (\Throwable $e) {
            Log::warning('ExportController::download recordDownload failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
        }

        return Storage::disk('local')->download($filePath, $fileName);
    }

    /**
     * GET /api/exports/settings
     * Return export settings for a tenant.
     */
    public function settings(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant context required.'], 422);
        }

        $requester = $this->resolveRequester($tenantId);
        if (!$requester) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $settings = $this->approvalService->getSettings($tenantId);

        return response()->json(['settings' => $settings]);
    }

    /**
     * PUT /api/exports/settings
     * Update export settings for a tenant (admin only).
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant context required.'], 422);
        }

        $requester = $this->resolveRequester($tenantId);
        if (!$requester) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$this->requesterIsAdmin($requester, $tenantId)) {
            return response()->json(['message' => 'Only tenant admins may update export settings.'], 403);
        }

        $validated = $request->validate([
            'require_approval_all'              => ['sometimes', 'boolean'],
            'allow_admin_direct'                => ['sometimes', 'boolean'],
            'allow_manager_direct'              => ['sometimes', 'boolean'],
            'allow_referrer_direct'             => ['sometimes', 'boolean'],
            'require_approval_referrer'         => ['sometimes', 'boolean'],
            'require_approval_manager'          => ['sometimes', 'boolean'],
            'block_partner'                     => ['sometimes', 'boolean'],
            'sensitive_always_require_approval' => ['sometimes', 'boolean'],
            'file_expiry_days'                  => ['sometimes', 'integer', 'min:1', 'max:90'],
            'notify_admin_on_request'           => ['sometimes', 'boolean'],
            'notify_requester_on_decision'      => ['sometimes', 'boolean'],
            'allow_requester_cancel'            => ['sometimes', 'boolean'],
            'require_reason_referrer'           => ['sometimes', 'boolean'],
            'require_reason_manager'            => ['sometimes', 'boolean'],
            'require_rejection_reason'          => ['sometimes', 'boolean'],
        ]);

        try {
            $merged = $this->approvalService->updateSettings($tenantId, $validated);
        } catch (\Throwable $e) {
            Log::error('ExportController::updateSettings failed', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Failed to update export settings.'], 500);
        }

        return response()->json(['settings' => $merged]);
    }
}
