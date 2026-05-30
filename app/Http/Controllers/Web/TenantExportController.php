<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ExportRequest;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\ExportApprovalService;
use App\Services\ExportPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TenantExportController extends Controller
{
    public function __construct(
        private ExportApprovalService   $approvalService,
        private ExportPermissionService $permissionService,
    ) {}

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveAdminUserId(): ?string
    {
        if (Auth::guard('web')->check()) {
            return (string) Auth::guard('web')->id();
        }

        if (Auth::guard('tenant')->check()) {
            return (string) Auth::guard('tenant')->id();
        }

        return null;
    }

    private function callerIsAdmin(string $tenantId): bool
    {
        if (Auth::guard('web')->check()) {
            return true;
        }

        if (Auth::guard('tenant')->check()) {
            $userId     = (string) Auth::guard('tenant')->id();
            $membership = TenantMembership::where('tenant_user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();

            return $membership && in_array($membership->role, ['owner', 'admin'], true);
        }

        return false;
    }

    // ── Web methods ───────────────────────────────────────────────────────────

    /**
     * GET /tenant/{tenantId}/exports
     * Export requests list page for Tenant Admin.
     */
    public function index(string $tenantId)
    {
        $tenant   = Tenant::findOrFail($tenantId);
        $settings = $this->approvalService->getSettings($tenantId);

        $filters = [
            'status' => request()->query('status', ''),
            'type'   => request()->query('type', ''),
            'role'   => request()->query('role', ''),
            'from'   => request()->query('from', ''),
            'to'     => request()->query('to', ''),
        ];

        return view('tenant.exports.index', compact('tenant', 'filters', 'settings'));
    }

    /**
     * GET /tenant/{tenantId}/exports/{exportId}
     * Export request detail and review page for Tenant Admin.
     */
    public function show(string $tenantId, string $exportId)
    {
        $tenant        = Tenant::findOrFail($tenantId);
        $exportRequest = ExportRequest::forTenant($tenantId)->where('id', $exportId)->firstOrFail();
        $settings      = $this->approvalService->getSettings($tenantId);

        return view('tenant.exports.show', compact('tenant', 'exportRequest', 'settings'));
    }

    /**
     * GET /tenant/{tenantId}/exports/{exportId}/download
     * Download the export file via web (auth-checked, streamed).
     */
    public function downloadWeb(string $tenantId, string $exportId)
    {
        $tenant        = Tenant::findOrFail($tenantId);
        $exportRequest = ExportRequest::forTenant($tenantId)->where('id', $exportId)->firstOrFail();

        // Determine current user identity
        $isAdmin = $this->callerIsAdmin($tenantId);

        // Check downloadability
        if (!$exportRequest->canBeDownloaded()) {
            abort(403, 'This export file is not available for download.');
        }

        // Non-admins must be the original requester
        if (!$isAdmin) {
            $userId = Auth::guard('tenant')->id() ?? Auth::guard('reseller')->id();
            $type   = Auth::guard('tenant')->check() ? 'tenant_user' : 'reseller';

            if ($exportRequest->requester_type !== $type
                || (string) $exportRequest->requester_id !== (string) $userId) {
                abort(403, 'You are not authorised to download this export.');
            }
        }

        $filePath = $exportRequest->file_path;
        $fileName = $exportRequest->file_name ?? 'export.' . ($exportRequest->export_format ?? 'csv');

        if (!$filePath || !str_starts_with($filePath, 'exports/')) {
            abort(422, 'Invalid export file path.');
        }

        if (!Storage::disk('local')->exists($filePath)) {
            abort(404, 'Export file not found on storage.');
        }

        try {
            $this->approvalService->recordDownload($exportRequest);
        } catch (\Throwable $e) {
            Log::warning('TenantExportController::downloadWeb recordDownload failed', [
                'id'    => $exportId,
                'error' => $e->getMessage(),
            ]);
        }

        return Storage::disk('local')->download($filePath, $fileName);
    }

    /**
     * GET /tenant/{tenantId}/exports/settings
     * Export settings section for the tenant.
     */
    public function settings(string $tenantId)
    {
        $tenant   = Tenant::findOrFail($tenantId);
        $settings = $this->approvalService->getSettings($tenantId);

        return view('tenant.exports.settings', compact('tenant', 'settings'));
    }
}
