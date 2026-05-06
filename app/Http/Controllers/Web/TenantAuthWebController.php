<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
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

    /**
     * Show workspace selector — shown when a user belongs to multiple tenants.
     */
    public function selectWorkspace(Request $request)
    {
        if (! Auth::guard('tenant')->check()) {
            return redirect()->route('tenant.login');
        }

        $user = Auth::guard('tenant')->user();

        $memberships = TenantMembership::where('tenant_user_id', $user->id)
            ->where('status', 'active')
            ->with('tenant')
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 WHEN 'manager' THEN 3 WHEN 'member' THEN 4 ELSE 5 END")
            ->get();

        if ($memberships->isEmpty()) {
            return redirect()->route('tenant.login')
                ->withErrors(['email' => 'No active tenant workspace found for your account.']);
        }

        if ($memberships->count() === 1) {
            return redirect()->route('tenant.dashboard', $memberships->first()->tenant_id);
        }

        return view('auth.tenant-select-workspace', compact('memberships', 'user'));
    }

    /**
     * Process workspace selection and redirect into the chosen tenant.
     */
    public function chooseWorkspace(Request $request)
    {
        if (! Auth::guard('tenant')->check()) {
            return redirect()->route('tenant.login');
        }

        $request->validate(['tenant_id' => ['required', 'string']]);

        $user = Auth::guard('tenant')->user();
        $tenantId = $request->input('tenant_id');

        $membership = TenantMembership::where('tenant_user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return back()->withErrors(['tenant_id' => 'You do not have access to that workspace.']);
        }

        // First-time invited user password review
        if ($membership->joined_by_invitation && ! $membership->password_review_completed) {
            session(['tenant_membership_id' => $membership->id]);
            return redirect()->route('tenant.first-signin-password');
        }

        return redirect()->route('tenant.dashboard', $tenantId);
    }

    private function redirectAfterLogin(TenantUser $user)
    {
        $memberships = TenantMembership::where('tenant_user_id', $user->id)
            ->where('status', 'active')
            ->with('tenant')
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

        // Multiple tenants — show workspace selector
        session(['pending_tenant_select' => $memberships->pluck('tenant_id')->toArray()]);
        return redirect()->route('tenant.select-workspace');
    }
}
