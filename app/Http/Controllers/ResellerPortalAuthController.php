<?php

namespace App\Http\Controllers;

use App\Events\InviteAcceptedEvent;
use App\Events\ResellerJoined;
use App\Http\Controllers\TenantLegalAgreementController;
use App\Mail\ResellerInvitation;
use App\Mail\ResellerPasswordReset;
use App\Models\Reseller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ResellerPortalAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('reseller')->check()) {
            $reseller = Auth::guard('reseller')->user();
            return redirect()->route('reseller.dashboard', $reseller->tenant_id);
        }
        return view('auth.reseller-login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $reseller = Reseller::where('email', strtolower(trim($credentials['email'])))->first();

        if (!$reseller) {
            return back()->withErrors(['email' => 'No referrer account found with this email.'])->withInput();
        }

        if (!$reseller->password) {
            return back()->withErrors(['email' => 'Account not activated yet. Please check your invitation email.'])->withInput();
        }

        if (!Hash::check($credentials['password'], $reseller->password)) {
            return back()->withErrors(['email' => 'Incorrect password. Please try again.'])->withInput();
        }

        if (!in_array($reseller->status, ['active', 'nda_signed'])) {
            return back()->withErrors(['email' => 'Your account is pending activation. Please contact your program administrator.'])->withInput();
        }

        Auth::guard('reseller')->login($reseller, $request->boolean('remember'));
        $request->session()->regenerate();

        // Redirect to agreements page if there are pending required agreements
        if (TenantLegalAgreementController::hasPending(
            $reseller->tenant_id, 'reseller', (string) $reseller->id, 'referrer'
        )) {
            return redirect()->route('tenant.legal-agreements.accept', $reseller->tenant_id);
        }

        return redirect()->route('reseller.dashboard', $reseller->tenant_id);
    }

    public function logout(Request $request)
    {
        Auth::guard('reseller')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('reseller.login');
    }

    public function showForgotPassword()
    {
        return view('auth.reseller-forgot-password');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $reseller = Reseller::where('email', strtolower(trim($request->email)))->first();

        // Always return success to prevent email enumeration
        if ($reseller) {
            $token      = Str::random(64);
            $tenantName = DB::table('tenants')->where('id', $reseller->tenant_id)->value('name') ?? 'Referral Bunny';
            $resetUrl   = url('/reseller/reset-password?token=' . $token);

            DB::table('resellers')->where('id', $reseller->id)->update(['setup_token' => $token]);

            try {
                Mail::send(new ResellerPasswordReset(
                    resellerName:  $reseller->name,
                    resellerEmail: $reseller->email,
                    tenantName:    $tenantName,
                    resetUrl:      $resetUrl,
                ));
            } catch (\Throwable $e) {
                Log::warning("Reseller password reset email failed: {$e->getMessage()}");
            }
        }

        return back()->with('success', 'If an account exists with that email, a reset link has been sent.');
    }

    public function showResetPassword(Request $request)
    {
        $token = $request->query('token');
        if (!$token) return redirect()->route('reseller.login');

        $reseller = Reseller::where('setup_token', $token)->first();
        if (!$reseller) {
            return redirect()->route('reseller.login')
                ->withErrors(['reset' => 'This reset link is invalid or has already been used.']);
        }

        return view('auth.reseller-reset-password', compact('token'));
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $reseller = Reseller::where('setup_token', $data['token'])->first();
        if (!$reseller) {
            return back()->withErrors(['token' => 'Invalid or expired reset link.']);
        }

        DB::table('resellers')->where('id', $reseller->id)->update([
            'password'    => Hash::make($data['password']),
            'setup_token' => null,
        ]);

        return redirect()->route('reseller.login')
            ->with('success', 'Password updated. You can now sign in.');
    }

    public function showSetup(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            return redirect()->route('reseller.login');
        }

        $reseller = Reseller::where('setup_token', $token)->first();
        if (!$reseller) {
            return view('auth.reseller-link-expired');
        }

        // Treat invitations older than 90 days as expired.
        if ($reseller->created_at && $reseller->created_at->lt(now()->subDays(90))) {
            return view('auth.reseller-link-expired');
        }

        return view('auth.reseller-setup', compact('reseller', 'token'));
    }

    public function setup(Request $request)
    {
        $data = $request->validate([
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $reseller = Reseller::where('setup_token', $data['token'])->first();
        if (!$reseller) {
            return back()->withErrors(['token' => 'Invalid or expired setup link.']);
        }

        // Block setup if the invitation is older than 90 days.
        if ($reseller->created_at && $reseller->created_at->lt(now()->subDays(90))) {
            return back()->withErrors(['token' => 'This setup link has expired. Please ask your workspace admin to resend the invitation.']);
        }

        DB::table('resellers')
            ->where('id', $reseller->id)
            ->update([
                'password'    => Hash::make($data['password']),
                'status'      => 'active',
                'setup_token' => null,
                'joined_date' => now()->toDateString(),
            ]);

        $reseller = $reseller->fresh();
        Auth::guard('reseller')->login($reseller);

        // Fire ResellerJoined → welcome email + admin in-app notification.
        try {
            ResellerJoined::dispatch(
                resellerId:    $reseller->id,
                resellerName:  $reseller->name,
                resellerEmail: $reseller->email,
                tenantId:      $reseller->tenant_id,
            );
        } catch (\Throwable $e) {
            Log::error('ResellerJoined event failed after account setup (non-fatal): ' . $e->getMessage(), [
                'reseller_id' => $reseller->id,
                'tenant_id'   => $reseller->tenant_id,
                'step'        => 'post_setup_event_dispatch',
            ]);
        }

        // Fire InviteAcceptedEvent → ActivityLog + AuditLog + inviter notification.
        // Admin notification is skipped for inviteType='reseller' (HandleResellerJoined already sent it).
        try {
            InviteAcceptedEvent::dispatch(
                tenantId:          $reseller->tenant_id,
                inviteType:        'reseller',
                acceptedUserId:    $reseller->id,
                acceptedUserName:  $reseller->name,
                acceptedUserEmail: $reseller->email,
                acceptedRole:      'referrer',
                invitedById:       $reseller->linked_tenant_user_id,
                inviteId:          null,
                relatedDealId:     null,
                relatedDealName:   null,
                acceptedAt:        now(),
            );
        } catch (\Throwable $e) {
            Log::warning('InviteAcceptedEvent dispatch failed for reseller: ' . $e->getMessage());
        }

        // Check for pending required legal agreements (referrer role)
        if (TenantLegalAgreementController::hasPending(
            $reseller->tenant_id, 'reseller', (string) $reseller->id, 'referrer'
        )) {
            return redirect()->route('tenant.legal-agreements.accept', $reseller->tenant_id);
        }

        return redirect()->route('reseller.dashboard', $reseller->tenant_id);
    }
}
