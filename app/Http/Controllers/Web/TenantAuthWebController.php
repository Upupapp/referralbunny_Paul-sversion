<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TenantAuthWebController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('tenant')->check()) {
            return $this->redirectAfterLogin(Auth::guard('tenant')->user());
        }

        return view('auth.tenant-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = TenantUser::whereRaw('lower(email) = ?', [strtolower($request->email)])->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return back()
                ->withErrors(['email' => 'Incorrect email or password. Please try again.'])
                ->withInput($request->only('email'));
        }

        if ($user->status !== 'active') {
            return back()
                ->withErrors(['email' => 'Your account is currently suspended. Contact your tenant admin.'])
                ->withInput($request->only('email'));
        }

        Auth::guard('tenant')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return $this->redirectAfterLogin($user);
    }

    public function logout(Request $request)
    {
        Auth::guard('tenant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login');
    }

    private function redirectAfterLogin(TenantUser $user)
    {
        $memberships = TenantMembership::where('tenant_user_id', $user->id)
            ->where('status', 'active')
            ->get();

        if ($memberships->isEmpty()) {
            return redirect()->route('tenant.login')
                ->withErrors(['email' => 'No active tenant workspace found for your account.']);
        }

        // Single tenant — go straight in
        if ($memberships->count() === 1) {
            $m = $memberships->first();

            // First-time invited user password review
            if ($m->joined_by_invitation && ! $m->password_review_completed) {
                session(['tenant_membership_id' => $m->id]);
                return redirect()->route('tenant.first-signin-password');
            }

            if (! $m->setup_completed) {
                return redirect()->route('tenant.onboarding', $m->tenant_id);
            }

            return redirect()->route('tenant.dashboard', $m->tenant_id);
        }

        // Multiple tenants — show selector
        return redirect()->route('tenant.select');
    }
}
