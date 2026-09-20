<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ResellerPortalAuthController;
use App\Models\User;
use App\Models\TenantUser;
use App\Models\Reseller;
use App\Models\Partner;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthWebController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        $email = strtolower(trim($credentials['email']));
        $request->merge(['email' => $email]);

        // Match credentials, not just email: a person can have accounts in several portals.
        // If the same credentials match several roles, use the most privileged active role.
        $accounts = [
            'web' => [User::class, null],
            'tenant' => [TenantUser::class, TenantAuthWebController::class],
            'reseller' => [Reseller::class, ResellerPortalAuthController::class],
            'partner' => [Partner::class, PartnerAuthController::class],
        ];
        foreach ($accounts as $guard => [$model, $controller]) {
            $user = $model::whereRaw('LOWER(email) = ?', [$email])->first();
            if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
                continue;
            }
            $active = $guard === 'web' || in_array($user->status, $guard === 'reseller' ? ['active', 'nda_signed'] : ['active'], true);
            if (! $active) {
                continue;
            }

            // Avoid stale portal sessions and intended URLs taking the user to another role.
            foreach (array_keys($accounts) as $otherGuard) {
                if ($otherGuard !== $guard) Auth::guard($otherGuard)->logout();
            }
            $request->session()->forget(['url.intended', 'legal_agreements.intended_url']);

            if ($controller) {
                return app($controller)->login($request);
            }
            Auth::guard('web')->login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            return redirect()->route('platform.dashboard');
        }

        return redirect()->route('login')
            ->withErrors(['email' => 'Unable to sign in. Check your email and password, or contact your administrator if your account is inactive.'])
            ->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect(route('login') . '?signed_out=1');
    }
}
