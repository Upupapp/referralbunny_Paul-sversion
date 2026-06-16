<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class PublicLandingController extends Controller
{
    public function index(Request $request)
    {
        return view('public.home', [
            'dashboardUrl' => $this->resolveDashboardUrl(),
        ]);
    }

    /**
     * Already-authenticated visitors get a "Go to Dashboard" link in the
     * header instead of (or alongside) Login/Build/Join. Guests get null,
     * which the header treats as "show the normal public CTAs".
     */
    private function resolveDashboardUrl(): ?string
    {
        if (Auth::guard('web')->check() && Route::has('platform.dashboard')) {
            return route('platform.dashboard');
        }

        if (Auth::guard('tenant')->check()) {
            $membership = Auth::guard('tenant')->user()->memberships()->first();
            if ($membership && Route::has('tenant.dashboard')) {
                return route('tenant.dashboard', $membership->tenant_id);
            }
        }

        if (Auth::guard('reseller')->check() && Route::has('reseller.dashboard')) {
            return route('reseller.dashboard', Auth::guard('reseller')->user()->tenant_id);
        }

        if (Auth::guard('partner')->check() && Route::has('partner.dashboard')) {
            return route('partner.dashboard');
        }

        return null;
    }
}
