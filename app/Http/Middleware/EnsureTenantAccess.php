<?php

namespace App\Http\Middleware;

use App\Models\TenantMembership;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

            $membership = TenantMembership::where('tenant_user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();

            if (! $membership) {
                abort(403, 'You do not have access to this tenant workspace.');
            }

            // Bind membership to request for downstream use (nav + controllers)
            $request->merge(['_tenant_membership' => $membership]);
            $request->attributes->set('_tenant_role', $membership->role ?? 'admin');

            return $next($request);
        }

        return redirect()->route('tenant.login');
    }
}
