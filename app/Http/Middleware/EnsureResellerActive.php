<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureResellerActive
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('reseller')->check()) {
            $reseller = Auth::guard('reseller')->user();
            if (!in_array($reseller->status, ['active', 'nda_signed'])) {
                Auth::guard('reseller')->logout();
                return redirect()->route('reseller.login')
                    ->with('error', 'Your account is not active. Please contact your program administrator.');
            }
        }

        return $next($request);
    }
}
