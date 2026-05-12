<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsurePartnerAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Check partner auth FIRST — a user can simultaneously hold a web (admin)
        // session and a partner session (common when an admin tests a partner invite
        // in their own browser). If the partner guard is active, allow through.
        if (Auth::guard('partner')->check()) {
            $partner = Auth::guard('partner')->user();

            if ($partner->status !== 'active') {
                Auth::guard('partner')->logout();
                return redirect()->route('partner.login')
                    ->withErrors(['email' => 'Account is not active. Please contact the workspace administrator.']);
            }

            // Verify partner belongs to the route's tenant (cross-tenant prevention)
            $routeTenantId = $request->route('tenantId');
            if ($routeTenantId && (string) $partner->tenant_id !== (string) $routeTenantId) {
                Auth::guard('partner')->logout();
                return redirect()->route('partner.login')
                    ->withErrors(['access' => 'You do not have access to this tenant.']);
            }

            return $next($request);
        }

        // Not authenticated as partner — redirect web admins to their portal,
        // everyone else to the partner login page.
        if (Auth::guard('web')->check()) {
            return redirect()->route('platform.dashboard');
        }

        return redirect()->route('partner.login');
    }
}
