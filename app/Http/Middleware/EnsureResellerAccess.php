<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureResellerAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Super admins use the tenant admin portal, not the reseller portal
        if (Auth::guard('web')->check()) {
            $tenantId = $request->route('tenantId');
            return redirect()->route('tenant.dashboard', $tenantId);
        }

        if (!Auth::guard('reseller')->check()) {
            return redirect()->route('reseller.login');
        }

        $reseller = Auth::guard('reseller')->user();
        $tenantId = $request->route('tenantId');

        if ($reseller->tenant_id !== $tenantId) {
            abort(403, 'You do not have access to this referral program.');
        }

        return $next($request);
    }
}
