<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsurePartnerAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Super admins and tenant admins use their own portals, not the partner portal
        if (Auth::guard('web')->check()) {
            return redirect()->route('platform.dashboard');
        }

        if (!Auth::guard('partner')->check()) {
            return redirect()->route('partner.login');
        }

        $partner = Auth::guard('partner')->user();

        if ($partner->status !== 'active') {
            Auth::guard('partner')->logout();
            return redirect()->route('partner.login')
                ->withErrors(['email' => 'Account is not active. Please contact the workspace administrator.']);
        }

        // NEW: Verify partner belongs to the route's tenant (cross-tenant prevention)
        $routeTenantId = $request->route('tenantId');
        if ($routeTenantId && (string) $partner->tenant_id !== (string) $routeTenantId) {
            Auth::guard('partner')->logout();
            return redirect()->route('partner.login')
                ->withErrors(['access' => 'You do not have access to this tenant.']);
        }

        return $next($request);
    }
}
