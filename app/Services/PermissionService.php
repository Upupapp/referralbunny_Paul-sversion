<?php

namespace App\Services;

use App\Models\TenantMembership;

class PermissionService
{
    // Manager permissions locked to false — cannot ever be enabled
    const MANAGER_LOCKED_FALSE = [
        'delete_tenant_account',
        'transfer_tenant_ownership',
        'remove_tenant_owner',
        'promote_self',
        'edit_own_permissions',
        'bypass_tenant_isolation',
        'modify_lgu_ids_locked_logic',
        'cancel_subscription',
    ];

    // Manager defaults — operational permissions enabled by default
    const MANAGER_DEFAULTS = [
        'view_dashboard'          => true,
        'view_deals'              => true,
        'create_deals'            => true,
        'edit_deals'              => true,
        'update_deal_stages'      => true,
        'view_organizations'      => true,
        'create_organizations'    => true,
        'edit_organizations'      => true,
        'view_contacts'           => true,
        'create_contacts'         => true,
        'edit_contacts'           => true,
        'import_contacts'         => true,
        'view_referrers'          => true,
        'invite_referrers'        => true,
        'view_partners'           => true,
        'invite_partners'         => true,
        'access_import_center'    => true,
        'import_deals'            => true,
        'view_reports'            => true,
        'generate_reports'        => true,
        'view_messages'           => true,
        'send_messages'           => true,
        'manage_own_notifications'=> true,
        'view_tenant_settings'    => true,
        'invite_tenant_managers'  => true,
        'invite_tenant_staff'     => true,
        // Disabled by default for Manager:
        'manage_billing_and_subscription' => false,
        'upgrade_subscription'    => false,
        'change_payment_method'   => false,
        'view_invoices'           => false,
        'delete_deals'            => false,
        'delete_contacts'         => false,
        'export_tenant_data'      => false,
        'edit_user_permissions'   => false,
        'export_reports'          => false,
        'request_data_export'     => false,
        'approve_export_requests' => false,
        'export_sensitive_data'   => false,
        // Hard locked:
        'delete_tenant_account'    => false,
        'transfer_tenant_ownership'=> false,
        'remove_tenant_owner'      => false,
        'cancel_subscription'      => false,
    ];

    const ADMIN_DEFAULTS = [
        'delete_tenant_account'    => false, // Only Owner
        'transfer_tenant_ownership'=> false, // Only Owner
    ];

    /**
     * Check if a membership has a specific permission.
     */
    public function can(TenantMembership $membership, string $permission): bool
    {
        $role = $membership->role;

        // Owner has all permissions
        if ($role === 'owner') return true;

        // Admin has all except ownership transfer/deletion
        if ($role === 'admin') {
            return !in_array($permission, ['delete_tenant_account', 'transfer_tenant_ownership', 'remove_tenant_owner']);
        }

        // Manager: check locked first
        if ($role === 'manager') {
            if (in_array($permission, self::MANAGER_LOCKED_FALSE)) return false;

            // Check custom override
            if ($membership->is_custom_permissions && $membership->permissions_json) {
                $perms = $membership->permissions_json;
                if (is_array($perms) && isset($perms[$permission])) {
                    return (bool) $perms[$permission];
                }
            }

            // Special billing flags stored as dedicated columns for reliability
            if ($permission === 'manage_billing_and_subscription') return (bool) $membership->can_manage_billing;
            if (in_array($permission, ['upgrade_subscription', 'change_payment_method', 'view_invoices'])) {
                return (bool) $membership->can_manage_billing;
            }

            return self::MANAGER_DEFAULTS[$permission] ?? false;
        }

        // Member/viewer: very limited
        $memberDefaults = ['view_dashboard', 'view_deals', 'view_contacts', 'view_reports', 'manage_own_notifications'];
        return in_array($permission, $memberDefaults);
    }

    /**
     * Get full permission map for a membership.
     */
    public function getAll(TenantMembership $membership): array
    {
        $role = $membership->role;

        if ($role === 'owner') return array_fill_keys(array_keys(self::MANAGER_DEFAULTS), true);
        if ($role === 'admin') return array_merge(array_fill_keys(array_keys(self::MANAGER_DEFAULTS), true), self::ADMIN_DEFAULTS);
        if ($role === 'manager') {
            $base = self::MANAGER_DEFAULTS;
            // Apply billing flag
            $billing = (bool) $membership->can_manage_billing;
            $base['manage_billing_and_subscription'] = $billing;
            $base['upgrade_subscription']  = $billing;
            $base['change_payment_method'] = $billing;
            $base['view_invoices']         = $billing;
            // Apply custom overrides
            if ($membership->is_custom_permissions && is_array($membership->permissions_json)) {
                foreach ($membership->permissions_json as $key => $val) {
                    if (!in_array($key, self::MANAGER_LOCKED_FALSE)) {
                        $base[$key] = (bool) $val;
                    }
                }
            }
            // Enforce locked
            foreach (self::MANAGER_LOCKED_FALSE as $locked) {
                $base[$locked] = false;
            }
            return $base;
        }
        // member/viewer
        return array_fill_keys(['view_dashboard', 'view_deals', 'view_contacts', 'view_reports', 'manage_own_notifications'], true);
    }

    /**
     * Check if a permission can be toggled for this role.
     * Returns true for locked permissions (meaning they cannot be changed).
     */
    public function isLocked(string $role, string $permission): bool
    {
        if ($role === 'manager') return in_array($permission, self::MANAGER_LOCKED_FALSE);
        return false;
    }

    /**
     * Update membership permissions — enforces locked constraints.
     * Only valid for manager role; no-op for other roles.
     */
    public function updateManagerPermissions(TenantMembership $membership, array $incoming): void
    {
        if ($membership->role !== 'manager') return;

        // Strip locked permissions from incoming — they cannot be changed
        foreach (self::MANAGER_LOCKED_FALSE as $locked) {
            unset($incoming[$locked]);
        }

        // Handle billing as a dedicated column for reliability
        if (array_key_exists('manage_billing_and_subscription', $incoming)) {
            $billing = (bool) $incoming['manage_billing_and_subscription'];
            $membership->can_manage_billing = $billing;
            // Cascade billing sub-permissions
            $incoming['upgrade_subscription']  = $billing;
            $incoming['change_payment_method'] = $billing;
            $incoming['view_invoices']         = $billing;
        }

        $existing = is_array($membership->permissions_json) ? $membership->permissions_json : [];
        $merged   = array_merge($existing, $incoming);

        // Re-enforce locked
        foreach (self::MANAGER_LOCKED_FALSE as $locked) {
            $merged[$locked] = false;
        }

        $membership->permissions_json      = $merged;
        $membership->is_custom_permissions = true;
        $membership->save();
    }
}
