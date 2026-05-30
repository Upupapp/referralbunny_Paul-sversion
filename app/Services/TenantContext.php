<?php
namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Support\Facades\Auth;

/**
 * Request-scoped tenant context. Always derive tenant from authentication,
 * never from user-supplied request parameters.
 */
class TenantContext
{
    private static ?string $tenantId    = null;
    private static ?Tenant $tenant      = null;
    private static ?string $userRole    = null;
    private static bool    $isSuperAdmin = false;

    public static function set(string $tenantId, Tenant $tenant, string $role, bool $isSuperAdmin = false): void
    {
        static::$tenantId     = $tenantId;
        static::$tenant       = $tenant;
        static::$userRole     = $role;
        static::$isSuperAdmin = $isSuperAdmin;
    }

    public static function id(): ?string        { return static::$tenantId; }
    public static function tenant(): ?Tenant    { return static::$tenant; }
    public static function isSuperAdmin(): bool { return static::$isSuperAdmin; }

    /**
     * Returns the current user's tenant role.
     * Falls back to request attributes set by EnsureTenantAccess middleware
     * when TenantContext::set() has not been explicitly called (web routes).
     */
    public static function role(): ?string
    {
        return static::$userRole
            ?? request()->attributes->get('_tenant_role');
    }
    public static function isReseller(): bool   { return static::role() === 'reseller'; }
    public static function isPartner(): bool    { return static::role() === 'partner'; }

    public static function requireId(): string
    {
        if (!static::$tenantId) {
            abort(403, 'No tenant context established.');
        }
        return static::$tenantId;
    }

    public static function clear(): void
    {
        static::$tenantId    = null;
        static::$tenant      = null;
        static::$userRole    = null;
        static::$isSuperAdmin = false;
    }

    /**
     * Resolve tenant context from the currently authenticated user.
     * Used by API middleware where no {tenantId} route param exists.
     * Returns null if user is super admin (platform-level access).
     */
    public static function resolveFromAuth(?string $requestedTenantId = null): ?string
    {
        // Super admin: allow all, derive from request param if provided
        if (Auth::guard('web')->check()) {
            static::$isSuperAdmin = true;
            static::$userRole     = 'super_admin';
            if ($requestedTenantId) {
                $tenant = Tenant::find($requestedTenantId);
                if ($tenant) {
                    static::$tenantId = $requestedTenantId;
                    static::$tenant   = $tenant;
                }
            }
            return static::$tenantId;
        }

        // Tenant user: must own membership in the requested tenant
        if (Auth::guard('tenant')->check()) {
            $user = Auth::guard('tenant')->user();
            // Validate against requested tenant if provided, else use first active membership
            $membershipQuery = TenantMembership::where('tenant_user_id', $user->id)
                ->where('status', 'active');
            if ($requestedTenantId) {
                $membershipQuery->where('tenant_id', $requestedTenantId);
            }
            $membership = $membershipQuery->first();
            if (!$membership) {
                abort(403, 'You do not have access to this tenant.');
            }
            $tenant = Tenant::find($membership->tenant_id);
            if (!$tenant) abort(404, 'Tenant not found.');
            static::$tenantId = $membership->tenant_id;
            static::$tenant   = $tenant;
            static::$userRole = $membership->role ?? 'viewer';
            return static::$tenantId;
        }

        // Reseller: always use their own tenant
        if (Auth::guard('reseller')->check()) {
            $reseller = Auth::guard('reseller')->user();
            if ($requestedTenantId && $reseller->tenant_id !== $requestedTenantId) {
                abort(403, 'You do not have access to this tenant.');
            }
            $tenant = Tenant::find($reseller->tenant_id);
            if (!$tenant) abort(404, 'Tenant not found.');
            static::$tenantId = $reseller->tenant_id;
            static::$tenant   = $tenant;
            static::$userRole = 'reseller';
            return static::$tenantId;
        }

        // Partner: always use their own tenant
        if (Auth::guard('partner')->check()) {
            $partner = Auth::guard('partner')->user();
            if ($requestedTenantId && $partner->tenant_id !== $requestedTenantId) {
                abort(403, 'You do not have access to this tenant.');
            }
            $tenant = Tenant::find($partner->tenant_id);
            if (!$tenant) abort(404, 'Tenant not found.');
            static::$tenantId = $partner->tenant_id;
            static::$tenant   = $tenant;
            static::$userRole = 'partner';
            return static::$tenantId;
        }

        return null;
    }
}
