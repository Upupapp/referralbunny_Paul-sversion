<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ContactRoleInvitation;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ContactRoleInviteWebController extends Controller
{
    // ── Show the invitation acceptance page ───────────────────────────────

    public function show(string $token)
    {
        $invitation = ContactRoleInvitation::where('token', $token)->first();

        if (!$invitation) {
            return view('contact-role-invite.show', [
                'error' => 'Invitation not found.',
            ]);
        }

        if (!$invitation->isValid()) {
            return view('contact-role-invite.show', [
                'error'  => $this->invalidReason($invitation),
                'status' => $invitation->status,
            ]);
        }

        // Referrer/Partner invitations are handled by their own portals
        if ($invitation->invited_role === 'referrer') {
            return redirect('/reseller/setup?token=' . $token);
        }
        if ($invitation->invited_role === 'partner') {
            return redirect('/partner/setup?token=' . $token);
        }

        $contact = DB::table('contacts')->where('id', $invitation->contact_id)->first();
        $tenant  = Tenant::find($invitation->tenant_id);

        // Check if email already has a TenantUser account
        $existingUser = TenantUser::whereRaw('LOWER(email) = ?', [strtolower($invitation->invited_email)])->first();

        return view('contact-role-invite.show', compact('invitation', 'contact', 'tenant', 'existingUser'));
    }

    // ── Handle acceptance (new or existing user) ──────────────────────────

    public function accept(Request $request, string $token)
    {
        $invitation = ContactRoleInvitation::where('token', $token)->first();

        if (!$invitation || !$invitation->isValid()) {
            return back()->withErrors(['token' => 'This invitation is no longer valid.']);
        }

        $request->validate([
            'name'     => 'required|string|max:150',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Find or create TenantUser
        $email = strtolower($invitation->invited_email);

        $tenantUser = TenantUser::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$tenantUser) {
            $nameParts  = explode(' ', trim($request->name), 2);
            $tenantUser = TenantUser::create([
                'id'         => (string) Str::uuid(),
                'first_name' => $nameParts[0],
                'last_name'  => $nameParts[1] ?? '',
                'email'      => $email,
                'password'   => Hash::make($request->password),
                'status'     => 'active',
            ]);
        } else {
            // Update password if they are setting up via invitation
            $tenantUser->update(['password' => Hash::make($request->password)]);
        }

        // Check for existing membership in this tenant
        $existingMembership = TenantMembership::where('tenant_id', $invitation->tenant_id)
            ->where('tenant_user_id', $tenantUser->id)
            ->first();

        if (!$existingMembership) {
            $role = match ($invitation->invited_role) {
                'tenant_manager' => 'manager',
                'tenant_staff'   => 'staff',
                default          => 'staff',
            };

            TenantMembership::create([
                'id'                   => (string) Str::uuid(),
                'tenant_id'            => $invitation->tenant_id,
                'tenant_user_id'       => $tenantUser->id,
                'role'                 => $role,
                'status'               => 'active',
                'joined_by_invitation' => true,
                'setup_completed'      => true,
                'invited_by_user_id'   => $invitation->invited_by_user_id,
                'joined_at'            => now(),
                'permissions_json'     => $invitation->permissions_json,
            ]);
        }

        // Update invitation status
        $invitation->update([
            'status'                => 'accepted',
            'accepted_at'           => now(),
            'linked_tenant_user_id' => $tenantUser->id,
        ]);

        // Update contact to link to user
        DB::table('contacts')
            ->where('id', $invitation->contact_id)
            ->update(['linked_user_id' => $tenantUser->id, 'updated_at' => now()]);

        // Audit log
        try {
            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $invitation->tenant_id,
                'user_id'   => $tenantUser->id,
                'action'    => 'contact_role_invite_accepted',
                'entity'    => 'contact',
                'entity_id' => $invitation->contact_id,
                'metadata'  => [
                    'assigned_role'    => $invitation->invited_role,
                    'tenant_user_id'   => $tenantUser->id,
                    'timestamp'        => now()->toIso8601String(),
                ],
            ]);
        } catch (\Throwable) {}

        // Log in the new tenant user
        Auth::guard('tenant')->login($tenantUser);

        return redirect('/tenant/' . $invitation->tenant_id . '/dashboard')
            ->with('success', 'Welcome! Your account has been set up successfully.');
    }

    private function invalidReason(ContactRoleInvitation $invitation): string
    {
        return match ($invitation->status) {
            'accepted' => 'This invitation has already been accepted.',
            'revoked'  => 'This invitation has been revoked.',
            'expired'  => 'This invitation has expired.',
            default    => 'This invitation is no longer valid.',
        };
    }
}
