<?php

namespace App\Services;

use App\Models\ExportRequest;
use App\Models\Reseller;
use App\Models\TenantConfig;
use App\Models\TenantMembership;

class ExportPermissionService
{
    /**
     * Default export settings when a tenant has not configured any overrides.
     * All settings are intentionally restrictive by default to protect data.
     */
    public function defaultSettings(): array
    {
        return [
            // Approval gates
            'require_approval_all'              => true,
            'allow_admin_direct'                => true,
            'allow_manager_direct'              => false,
            'allow_referrer_direct'             => false,
            'require_approval_referrer'         => true,
            'require_approval_manager'          => true,

            // Access control
            'block_partner'                     => true,
            'sensitive_always_require_approval' => true,

            // File lifecycle
            'file_expiry_days'                  => 7,

            // Notification triggers
            'notify_admin_on_request'           => true,
            'notify_requester_on_decision'      => true,

            // Requester capabilities
            'allow_requester_cancel'            => true,
            'require_reason_referrer'           => true,
            'require_reason_manager'            => true,
            'require_rejection_reason'          => true,
        ];
    }

    /**
     * Return merged settings: defaults overridden by whatever the tenant has stored.
     * Reads export_settings from tenant_configs; falls back cleanly if not present.
     */
    public function getSettings(string $tenantId): array
    {
        $config = TenantConfig::where('tenant_id', $tenantId)->first();

        $stored = [];
        if ($config) {
            // export_settings may not yet be a recognised cast on older model versions;
            // retrieve directly from the raw attributes to be safe.
            $raw = $config->getAttributes()['export_settings'] ?? null;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $stored  = is_array($decoded) ? $decoded : [];
            } elseif (is_array($raw)) {
                $stored = $raw;
            }
        }

        return array_merge($this->defaultSettings(), $stored);
    }

    /**
     * Determine whether an export type is classified as sensitive.
     */
    public function isSensitive(string $exportType): bool
    {
        return in_array($exportType, ExportRequest::SENSITIVE_TYPES, true);
    }

    /**
     * Check whether a TenantMembership may initiate an export request.
     *
     * Returns:
     *   allowed           – whether the role is permitted to export at all
     *   requires_approval – true when the request must go through the approval flow
     *   reason            – human-readable explanation when denied or when approval is needed
     */
    public function checkTenantUserExport(
        TenantMembership $membership,
        string           $exportType,
        array            $settings,
    ): array {
        $role = $membership->role;

        // member and viewer roles have no export capability
        if (in_array($role, ['member', 'viewer'], true)) {
            return [
                'allowed'           => false,
                'requires_approval' => false,
                'reason'            => 'Your role does not have export permissions.',
            ];
        }

        // Sensitive type check – always force approval when tenant setting demands it
        $sensitive              = $this->isSensitive($exportType);
        $sensitiveForceApproval = $sensitive && ($settings['sensitive_always_require_approval'] ?? true);

        // Owner and Admin
        if (in_array($role, ['owner', 'admin'], true)) {
            $allowDirect    = (bool) ($settings['allow_admin_direct'] ?? true);
            $forceApproval  = (bool) ($settings['require_approval_all'] ?? false);
            $requireApproval = $forceApproval || ($sensitiveForceApproval && !$allowDirect);

            // When allow_admin_direct is true and not globally overridden, they go direct
            if ($allowDirect && !$forceApproval && !$sensitiveForceApproval) {
                return [
                    'allowed'           => true,
                    'requires_approval' => false,
                    'reason'            => '',
                ];
            }

            return [
                'allowed'           => true,
                'requires_approval' => $requireApproval,
                'reason'            => $requireApproval
                    ? ($sensitiveForceApproval ? 'Sensitive exports require admin approval.' : 'All exports require approval per tenant settings.')
                    : '',
            ];
        }

        // Manager
        if ($role === 'manager') {
            // Manager needs explicit export permission
            $permissionService = app(PermissionService::class);
            $hasExportData    = $permissionService->can($membership, 'export_tenant_data');
            $hasExportReports = $permissionService->can($membership, 'export_reports');

            if (!$hasExportData && !$hasExportReports) {
                return [
                    'allowed'           => false,
                    'requires_approval' => false,
                    'reason'            => 'You do not have permission to export data. Contact your administrator.',
                ];
            }

            $allowDirect    = (bool) ($settings['allow_manager_direct'] ?? false);
            $requireApproval = (bool) ($settings['require_approval_manager'] ?? true);

            // If globally all require approval, or sensitive types force it, approval is needed
            if ((bool) ($settings['require_approval_all'] ?? true) || $sensitiveForceApproval) {
                $requireApproval = true;
            }

            if ($allowDirect && !$requireApproval) {
                return [
                    'allowed'           => true,
                    'requires_approval' => false,
                    'reason'            => '',
                ];
            }

            return [
                'allowed'           => true,
                'requires_approval' => true,
                'reason'            => $sensitiveForceApproval
                    ? 'Sensitive exports require admin approval.'
                    : 'Manager exports require approval per tenant settings.',
            ];
        }

        return [
            'allowed'           => false,
            'requires_approval' => false,
            'reason'            => 'Your role does not have export permissions.',
        ];
    }

    /**
     * Check whether a Referrer (Reseller) may initiate an export request.
     * Referrers may only ever request exports of their own data.
     *
     * Returns same shape as checkTenantUserExport().
     */
    public function checkReferrerExport(
        Reseller $reseller,
        string   $exportType,
        array    $settings,
    ): array {
        // Partners are blocked regardless – handled by checkPartnerExport() but
        // guard here too since Reseller rows should never be 'partner' type.
        if ((bool) ($settings['block_partner'] ?? true)) {
            // Resellers are referrers, not partners – this is a safety guard
        }

        // Referrers may only export types relevant to their own data
        $referrerAllowedTypes = ['deals', 'contacts', 'referrers', 'commissions'];
        if (!in_array($exportType, $referrerAllowedTypes, true)) {
            return [
                'allowed'           => false,
                'requires_approval' => false,
                'reason'            => 'Referrers may only export their own deals, contacts, commissions, or referrer data.',
            ];
        }

        $sensitive              = $this->isSensitive($exportType);
        $sensitiveForceApproval = $sensitive && ((bool) ($settings['sensitive_always_require_approval'] ?? true));

        $allowDirect       = (bool) ($settings['allow_referrer_direct'] ?? false);
        $requireApproval   = (bool) ($settings['require_approval_referrer'] ?? true);
        $canExportOwn      = (bool) ($reseller->can_export_own_data ?? false);

        // If the referrer has no individual grant at all
        if (!$canExportOwn) {
            return [
                'allowed'           => false,
                'requires_approval' => false,
                'reason'            => 'You have not been granted export permissions. Contact your administrator.',
            ];
        }

        // Force approval for globally required or sensitive types
        if ((bool) ($settings['require_approval_all'] ?? true) || $sensitiveForceApproval) {
            $requireApproval = true;
        }

        // Direct path: tenant allows referrer direct, no global approval override, not sensitive forced
        if ($allowDirect && !$requireApproval) {
            return [
                'allowed'           => true,
                'requires_approval' => false,
                'reason'            => '',
            ];
        }

        return [
            'allowed'           => true,
            'requires_approval' => true,
            'reason'            => $sensitiveForceApproval
                ? 'Sensitive exports require admin approval.'
                : 'Your export request will be reviewed by an administrator.',
        ];
    }

    /**
     * Partners are always denied export access.
     */
    public function checkPartnerExport(): array
    {
        return [
            'allowed'           => false,
            'requires_approval' => false,
            'reason'            => 'Partners are not permitted to export data from this platform.',
        ];
    }

    /**
     * Check whether a TenantMembership can approve export requests.
     * Only owners and admins may approve.
     */
    public function canApprove(TenantMembership $membership): bool
    {
        return in_array($membership->role, ['owner', 'admin'], true);
    }

    /**
     * Check whether the given actor can download a specific ExportRequest.
     * Only the original requester may download their own export.
     */
    public function canDownload(
        ExportRequest $request,
        string        $requesterType,
        string        $requesterId,
    ): bool {
        if (!$request->canBeDownloaded()) {
            return false;
        }

        return $request->requester_type === $requesterType
            && (string) $request->requester_id === (string) $requesterId;
    }

    /**
     * Check whether the given actor can cancel a specific ExportRequest.
     * The requester may cancel their own pending request.
     * Admins/owners may cancel any pending request in their tenant.
     */
    public function canCancel(
        ExportRequest $request,
        string        $requesterType,
        string        $requesterId,
    ): bool {
        if (!$request->canBeCancelled()) {
            return false;
        }

        // The original requester can always cancel their own request
        if ($request->requester_type === $requesterType
            && (string) $request->requester_id === (string) $requesterId) {
            return true;
        }

        // Tenant admins/owners can cancel any request in their tenant
        if ($requesterType === 'tenant_user') {
            $membership = TenantMembership::where('tenant_user_id', $requesterId)
                ->where('tenant_id', $request->tenant_id)
                ->where('status', 'active')
                ->first();

            if ($membership && in_array($membership->role, ['owner', 'admin'], true)) {
                return true;
            }
        }

        return false;
    }
}
