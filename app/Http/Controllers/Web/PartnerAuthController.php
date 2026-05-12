<?php

namespace App\Http\Controllers\Web;

use App\Events\InviteAcceptedEvent;
use App\Http\Controllers\Controller;
use App\Mail\PartnerPasswordReset;
use App\Models\DealPartner;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PartnerAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('partner')->check()) {
            return redirect()->route('partner.dashboard');
        }

        return view('auth.partner-login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $partner = Partner::where('email', strtolower(trim($credentials['email'])))->first();

        if (!$partner) {
            return back()->withErrors(['email' => 'No partner account found.'])->withInput();
        }

        if (!$partner->password) {
            return back()->withErrors(['email' => 'Account not activated.'])->withInput();
        }

        if ($partner->status !== 'active') {
            return back()->withErrors(['email' => 'Account not activated.'])->withInput();
        }

        if (!Hash::check($credentials['password'], $partner->password)) {
            return back()->withErrors(['email' => 'Incorrect password.'])->withInput();
        }

        Auth::guard('partner')->login($partner, $request->boolean('remember'));

        return redirect()->route('partner.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('partner')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('partner.login');
    }

    public function showSetup(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            return redirect()->route('partner.login');
        }

        $partner = Partner::where('setup_token', $token)->first();
        if (!$partner) {
            return redirect()->route('partner.login')
                ->withErrors(['token' => 'This setup link is invalid or has already been used.']);
        }

        return view('auth.partner-setup', compact('partner', 'token'));
    }

    public function setup(Request $request)
    {
        $data = $request->validate([
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $partner = Partner::where('setup_token', $data['token'])->first();
        if (!$partner) {
            return back()->withErrors(['token' => 'Invalid or expired setup link.']);
        }

        try {
            $partner->update([
                'password'           => $data['password'],
                'status'             => 'active',
                'setup_completed_at' => now(),
                'setup_token'        => null,
                'last_login_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('PartnerAuthController::setup — update failed', [
                'partner_id' => $partner->id,
                'error'      => $e->getMessage(),
            ]);
            return back()->withErrors(['token' => 'Account activation failed. Please contact support. (' . $e->getMessage() . ')']);
        }

        // Activate any pending DealPartner invitations for this partner
        try {
            DealPartner::where('partner_user_id', $partner->id)
                ->where('status', 'invited')
                ->update(['status' => 'active', 'accepted_at' => now()]);
        } catch (\Throwable) {}

        // If this partner was invited via the referrer/deal flow (no DealPartner record),
        // link their DealPartnerSplit rows so the portal can find their deals.
        try {
            $splitIds = DB::table('deal_partner_splits')
                ->where('tenant_id', $partner->tenant_id)
                ->whereRaw('LOWER(partner_email) = ?', [strtolower($partner->email)])
                ->whereNull('deleted_at')
                ->where('status', '!=', 'removed')
                ->pluck('id');

            if ($splitIds->isNotEmpty()) {
                DB::table('deal_partner_splits')
                    ->whereIn('id', $splitIds)
                    ->update(['partner_user_id' => $partner->id]);
            }
        } catch (\Throwable $e) {
            Log::warning('PartnerAuthController: could not link split rows to partner', [
                'partner_id' => $partner->id, 'error' => $e->getMessage(),
            ]);
        }

        // Fetch a related deal (if any) for the notification
        $relatedDealPartner = null;
        $relatedDealName    = null;
        try {
            $relatedDealPartner = DealPartner::where('partner_user_id', $partner->id)
                ->where('status', 'active')
                ->first();

            if ($relatedDealPartner?->deal_id) {
                // Table is 'leads', not 'deals'
                $relatedDealName = DB::table('leads')
                    ->where('id', $relatedDealPartner->deal_id)
                    ->value('name');
            }
        } catch (\Throwable) {}

        Auth::guard('partner')->login($partner->fresh());

        // Fire unified invite-accepted event (ActivityLog + AuditLog + in-app notifications)
        try {
            InviteAcceptedEvent::dispatch(
                tenantId:          $partner->tenant_id,
                inviteType:        'partner',
                acceptedUserId:    $partner->id,
                acceptedUserName:  $partner->full_name ?: $partner->email,
                acceptedUserEmail: $partner->email,
                acceptedRole:      'partner',
                invitedById:       ($partner->invited_by_type === 'tenant_user') ? $partner->invited_by_id : null,
                inviteId:          null,
                relatedDealId:     $relatedDealPartner?->deal_id,
                relatedDealName:   $relatedDealName,
                acceptedAt:        now(),
            );
        } catch (\Throwable $e) {
            Log::warning('InviteAcceptedEvent dispatch failed for partner: ' . $e->getMessage());
        }

        return redirect()->route('partner.dashboard');
    }

    public function showInvite(string $token)
    {
        $partner = Partner::where('setup_token', $token)->first();
        if (!$partner) {
            return redirect()->route('partner.login')
                ->withErrors(['token' => 'This invite link is invalid or has already been used.']);
        }

        return view('auth.partner-setup', compact('partner', 'token'));
    }

    public function showForgotPassword()
    {
        return view('auth.partner-forgot-password');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $partner = Partner::where('email', strtolower(trim($request->email)))->first();

        // Always return success to prevent email enumeration
        if ($partner && $partner->status === 'active') {
            $token    = Str::random(64);
            $resetUrl = url('/partner/reset-password?token=' . $token);

            $partner->update(['setup_token' => $token]);

            try {
                Mail::send(new PartnerPasswordReset(
                    partnerName:  $partner->full_name ?: $partner->email,
                    partnerEmail: $partner->email,
                    tenantName:   $partner->tenant?->name ?? 'ReferralBunny',
                    resetUrl:     $resetUrl,
                ));
            } catch (\Throwable $e) {
                Log::warning("Partner password reset email failed: {$e->getMessage()}");
            }
        }

        return back()->with('success', 'If an account exists with that email, a reset link has been sent.');
    }

    public function showResetPassword(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            return redirect()->route('partner.login');
        }

        $partner = Partner::where('setup_token', $token)->first();
        if (!$partner) {
            return redirect()->route('partner.login')
                ->withErrors(['reset' => 'This reset link is invalid or has already been used.']);
        }

        return view('auth.partner-reset-password', compact('token'));
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $partner = Partner::where('setup_token', $data['token'])->first();
        if (!$partner) {
            return back()->withErrors(['token' => 'Invalid or expired reset link.']);
        }

        $partner->update([
            'password'    => $data['password'],
            'setup_token' => null,
        ]);

        return redirect()->route('partner.login')
            ->with('success', 'Password updated. You can now sign in with your new password.');
    }
}
