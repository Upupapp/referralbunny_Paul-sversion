<?php

namespace App\Http\Middleware;

use App\Models\TenantMembership;
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
            $tenantId = $request->route('tenantId');
            $userId   = Auth::guard('tenant')->id();

            $cacheKey = "tenant_membership:{$userId}:{$tenantId}";
            $cached   = Cache::remember($cacheKey, 120, fn() =>
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

            return $next($request);
        }

        return redirect()->route('tenant.login');
    }
}
