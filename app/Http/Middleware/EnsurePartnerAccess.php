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

            if (!$partner->tenant_id || $partner->status !== 'active') {
                Auth::guard('partner')->logout();
                return redirect()->route('partner.login')
                    ->withErrors(['email' => 'Account is not active. Please contact the workspace administrator.']);
            }

            // Partner routes have no {tenantId} segment — tenant isolation is enforced
            // via auth('partner')->user()->tenant_id in every controller, not the URL.

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
