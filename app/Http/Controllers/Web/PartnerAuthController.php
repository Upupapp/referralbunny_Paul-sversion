<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DealPartner;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        $partner->update([
            'password'           => Hash::make($data['password']),
            'status'             => 'active',
            'setup_completed_at' => now(),
            'setup_token'        => null,
            'last_login_at'      => now(),
        ]);

        // Activate any pending DealPartner invitations for this partner
        DealPartner::where('partner_user_id', $partner->id)
            ->where('status', 'invited')
            ->update([
                'status'      => 'active',
                'accepted_at' => now(),
            ]);

        Auth::guard('partner')->login($partner);

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
}
