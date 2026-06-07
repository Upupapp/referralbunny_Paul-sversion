<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Ensures a user can only access a tenant they belong to.
 *
 * - Super admins (web guard) → always allowed through
 * - Tenant users (tenant guard) → must have an active membership for {tenantId}
 */
class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Super admin session — full access to any tenant
        if (Auth::guard('web')->check()) {
            return $next($request);
        }

        // Tenant user — must belong to the requested tenant
        if (Auth::guard('tenant')->check()) {
            $tenantUser = Auth::guard('tenant')->user();
            if ($tenantUser->status !== 'active') {
                abort(403, 'Your account has been suspended.');
            }

            $tenantId = $request->route('tenantId');

            // Verify the tenant itself is not suspended/cancelled
            $tenantStatus = Cache::remember("tenant_status:{$tenantId}", 60, fn() =>
                Tenant::where('id', $tenantId)->value('status')
            );
            if ($tenantStatus && in_array($tenantStatus, ['inactive', 'suspended', 'cancelled'], true)) {
                abort(403, 'This workspace is no longer active. Please contact support.');
            }
            $userId   = Auth::guard('tenant')->id();

            $cacheKey = "tenant_membership:{$userId}:{$tenantId}";
            $cached   = Cache::remember($cacheKey, 30, fn() =>
                TenantMembership::where('tenant_user_id', $userId)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->select(['id', 'tenant_user_id', 'tenant_id', 'role', 'status'])
                    ->first()?->toArray()
            );

            if (! $cached) {
                abort(403, 'You do not have access to this tenant workspace.');
            }

            $membership = (object) $cached;

            // Bind membership to request for downstream use (nav + controllers)
            $request->merge(['_tenant_membership' => $cached]);
            $request->attributes->set('_tenant_role', $cached['role'] ?? 'viewer');

            // Set TenantContext so web-route controllers and traits (assertAdminContext,
            // isAdminOrManager, TenantContext::id()) work the same as API routes.
            // SetApiTenantContext only runs on /api/* — this fills the same gap for web.
            try {
                $tenant = Cache::remember("tenant_model:{$tenantId}", 60, fn() => Tenant::find($tenantId));
            } catch (\Throwable) {
                // Cache driver failure or model deserialization error — fall back to direct lookup.
                $tenant = Tenant::find($tenantId);
            }
            if ($tenant) {
                TenantContext::set($tenantId, $tenant, $cached['role'] ?? 'viewer');
            }

            return $next($request);
        }

        return redirect()->route('tenant.login');
    }
}
