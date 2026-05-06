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

        return $next($request);
    }
}
