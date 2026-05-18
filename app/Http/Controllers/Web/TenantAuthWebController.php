<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\TenantPasswordResetMail;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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

        return redirect(route('tenant.login') . '?signed_out=1');
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
            return redirect()->intended(route('tenant.dashboard', $memberships->first()->tenant_id));
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

        return redirect()->route('tenant.dashboard', $tenantId);
    }

    // ── Forgot / Reset Password ──────────────────────────────────────────────

    public function showForgotPassword()
    {
        return view('auth.tenant-forgot-password');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = TenantUser::whereRaw('lower(email) = ?', [strtolower(trim($request->email))])->first();

        // Always return success to prevent email enumeration
        if ($user) {
            $token    = Str::random(64);
            $resetUrl = url('/tenant/reset-password?token=' . $token . '&email=' . urlencode($user->email));

            DB::table('password_reset_tokens')->upsert(
                ['email' => $user->email, 'token' => Hash::make($token), 'created_at' => now()],
                ['email'],
                ['token', 'created_at']
            );

            try {
                Mail::queue(new TenantPasswordResetMail(
                    userName:  trim("{$user->first_name} {$user->last_name}"),
                    userEmail: $user->email,
                    resetUrl:  $resetUrl,
                ));
            } catch (\Throwable $e) {
                Log::warning('[TenantAuth] Password reset email failed', ['error' => $e->getMessage()]);
            }
        }

        return back()->with('success', 'If an account exists with that email, a reset link has been sent.');
    }

    public function showResetPassword(Request $request)
    {
        $token = $request->query('token');
        $email = $request->query('email');
        if (!$token || !$email) return redirect()->route('tenant.login');

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('created_at', '>', now()->subHour())
            ->first();

        if (!$record || !Hash::check($token, $record->token)) {
            return redirect()->route('tenant.login')
                ->withErrors(['reset' => 'This reset link is invalid or has expired. Please request a new one.']);
        }

        return view('auth.tenant-reset-password', compact('token', 'email'));
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token'                 => 'required|string',
            'email'                 => 'required|email',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->where('created_at', '>', now()->subHour())
            ->first();

        if (!$record || !Hash::check($data['token'], $record->token)) {
            return back()->withErrors(['token' => 'This reset link is invalid or has expired.']);
        }

        $user = TenantUser::whereRaw('lower(email) = ?', [strtolower($data['email'])])->first();
        if (!$user) {
            return back()->withErrors(['email' => 'No account found with that email address.']);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return redirect()->route('tenant.login')
            ->with('status', 'Password updated successfully. You can now sign in with your new password.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

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

            return redirect()->intended(route('tenant.dashboard', $m->tenant_id));
        }

        // Multiple tenants — show workspace selector
        session(['pending_tenant_select' => $memberships->pluck('tenant_id')->toArray()]);
        return redirect()->route('tenant.select-workspace');
    }
}
