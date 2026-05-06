<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class TenantSignupWebController extends Controller
{
    public function showBuild()
    {
        // Redirect to dashboard if already logged in
        if (Auth::guard('tenant')->check()) {
            $tenantId = DB::table('tenant_memberships')
                ->where('tenant_user_id', Auth::guard('tenant')->id())
                ->where('status', 'active')
                ->value('tenant_id');
            if ($tenantId) return redirect()->route('tenant.dashboard', $tenantId);
        }

        return view('auth.build-program');
    }

    public function build(Request $request)
    {
        $data = $request->validate([
            'first_name'         => 'required|string|max:100',
            'last_name'          => 'required|string|max:100',
            'email'              => 'required|email|unique:tenant_users,email',
            'password'           => ['required', 'confirmed', Password::min(8)],
            'workspace_name'     => 'required|string|max:255',
            'industry'           => 'required|string|max:100',
            'country'            => 'required|string|max:100',
            'timezone'           => 'required|string|max:100',
            'preferred_currency' => 'required|string|max:10',
            'terms'              => 'accepted',
        ]);

        // Generate a URL-safe tenant ID from the workspace name
        $base     = Str::slug($data['workspace_name']);
        $tenantId = $base . '-' . Str::lower(Str::random(6));

        // Ensure uniqueness
        while (DB::table('tenants')->where('id', $tenantId)->exists()) {
            $tenantId = $base . '-' . Str::lower(Str::random(6));
        }

        $userId = (string) Str::uuid();

        DB::transaction(function () use ($data, $tenantId, $userId) {
            // Create tenant user account
            DB::table('tenant_users')->insert([
                'id'         => $userId,
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create tenant workspace
            DB::table('tenants')->insert([
                'id'                 => $tenantId,
                'name'               => $data['workspace_name'],
                'program_name'       => $data['workspace_name'],
                'industry'           => $data['industry'],
                'country'            => $data['country'],
                'timezone'           => $data['timezone'],
                'preferred_currency' => $data['preferred_currency'],
                'status'             => 'active',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // Assign as owner
            DB::table('tenant_memberships')->insert([
                'id'             => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'tenant_user_id' => $userId,
                'role'           => 'owner',
                'status'         => 'active',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        });

        // Log in as tenant user
        Auth::guard('tenant')->loginUsingId($userId, false);

        return redirect()->route('tenant.dashboard', $tenantId)
            ->with('success', "Welcome! Your workspace \"{$data['workspace_name']}\" is ready.");
    }

    public function showJoin()
    {
        return view('auth.join-program');
    }

    /** Handle invite token web redirect (for links like /tenant/invite/{token}) */
    public function showInvite(string $token)
    {
        // Check reseller setup token first
        $reseller = DB::table('resellers')->where('setup_token', $token)->first();
        if ($reseller) {
            return redirect()->route('reseller.setup', ['token' => $token]);
        }

        // Check tenant invitation token — delegate to the canonical invite acceptance flow
        $invite = DB::table('tenant_invitations')->where('token', $token)->first();

        if (!$invite) {
            return redirect()->route('tenant.login')
                ->withErrors(['email' => 'This invite link is invalid or does not exist.']);
        }

        if ($invite->status === 'accepted') {
            return redirect()->route('tenant.login')
                ->with('success', 'This invitation has already been accepted. Please sign in.');
        }

        if ($invite->status === 'revoked' || ($invite->expires_at && now()->gt($invite->expires_at))) {
            // Reuse the canonical expired-invite view (token is just used for display context)
            return view('auth.tenant-invite-expired', ['invitation' => null]);
        }

        // Valid invite — hand off to the canonical acceptance route
        return redirect()->route('tenant.accept-invite.show', $token);
    }
}
