<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantInvitationController extends Controller
{
    /** GET /auth/tenant/invite/{token} — validate without consuming */
    public function validate(string $token): JsonResponse
    {
        $invite = TenantInvitation::with('tenant')->where('token', $token)->first();

        if (! $invite) {
            return response()->json(['message' => 'This invitation link is invalid.', 'status' => 'invalid'], 404);
        }

        if ($invite->status === 'accepted') {
            return response()->json(['message' => 'This invite was already accepted.', 'status' => 'accepted'], 409);
        }

        if ($invite->status === 'revoked') {
            return response()->json(['message' => 'This invitation has been revoked.', 'status' => 'revoked'], 410);
        }

        if ($invite->status === 'expired' || now()->gt($invite->expires_at)) {
            $invite->update(['status' => 'expired']);
            return response()->json(['message' => 'This invitation has expired.', 'status' => 'expired'], 410);
        }

        return response()->json([
            'id'          => $invite->id,
            'tenant_id'   => $invite->tenant_id,
            'tenant_name' => $invite->tenant?->name,
            'email'       => $invite->email,
            'role'        => $invite->role,
            'expires_at'  => $invite->expires_at,
            'status'      => $invite->status,
        ]);
    }

    /** POST /auth/tenant/invite/{token}/accept — accept invite + create account */
    public function accept(Request $request, string $token): JsonResponse
    {
        $invite = TenantInvitation::with('tenant')->where('token', $token)->first();

        if (! $invite || ! $invite->isValid()) {
            return response()->json(['message' => 'Invalid or expired invitation.', 'status' => 'invalid'], 410);
        }

        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'password'   => 'required|string|min:8|confirmed',
        ]);

        $user = TenantUser::whereRaw('lower(email) = ?', [strtolower($invite->email)])->first();

        if (! $user) {
            $user = TenantUser::create([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'email'      => strtolower($invite->email),
                'password'   => Hash::make($request->password),
                'status'     => 'active',
            ]);
        } else {
            // Existing account: require current password to prevent invitation link hijacking
            $request->validate(['current_password' => 'required|string']);
            if (!Hash::check($request->input('current_password'), $user->password)) {
                return response()->json(['message' => 'Current password is incorrect.'], 422);
            }
        }

        $membership = TenantMembership::updateOrCreate(
            ['tenant_id' => $invite->tenant_id, 'tenant_user_id' => $user->id],
            [
                'role'                      => $invite->role,
                'status'                    => 'active',
                'joined_by_invitation'      => true,
                'password_review_completed' => false,
                'joined_at'                 => now(),
            ]
        );

        // Bust stale tm_role cache so the new membership is visible immediately on /api/leads
        Cache::forget("tm_role:{$user->id}:{$invite->tenant_id}");

        $invite->update(['status' => 'accepted', 'accepted_at' => now()]);

        $apiToken = $user->createToken('tenant-api-token')->plainTextToken;

        return response()->json([
            'user'        => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
                'status'     => $user->status,
            ],
            'token'       => $apiToken,
            'memberships' => [[
                'id'                        => $membership->id,
                'tenant_id'                 => $invite->tenant_id,
                'tenant_name'               => $invite->tenant?->name,
                'tenant_slug'               => $invite->tenant?->slug,
                'role'                      => $invite->role,
                'status'                    => 'active',
                'setup_completed'           => false,
                'joined_by_invitation'      => true,
                'password_review_completed' => false,
            ]],
        ], 201);
    }

    /** POST /tenant-invitations — create invite (protected, tenant admin only) */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|string|exists:tenants,id',
            'email'     => 'required|email|max:255',
            'role'      => 'nullable|in:admin,manager,member,viewer',
        ]);

        // Non-SA callers can only invite into their own tenant, and must be owner or admin
        if (!TenantContext::isSuperAdmin()) {
            $tenantId = TenantContext::requireId();
            if ($request->tenant_id !== $tenantId) {
                abort(403, 'You can only invite users into your own workspace.');
            }
            if (!in_array(TenantContext::role(), ['owner', 'admin'])) {
                abort(403, 'Only owners and admins can invite team members.');
            }
        }

        $invite = TenantInvitation::create([
            'tenant_id'  => $request->tenant_id,
            'email'      => strtolower($request->email),
            'role'       => $request->role ?? 'admin',
            'invited_by' => $request->user()?->id,
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'invitation' => $invite,
            'invite_url' => url('/tenant/invite/' . $invite->token),
        ], 201);
    }

    /** DELETE /tenant-invitations/{id} — revoke invite */
    public function revoke(string $id): JsonResponse
    {
        $invite = TenantInvitation::findOrFail($id);

        if (!TenantContext::isSuperAdmin()) {
            $tenantId = TenantContext::requireId();
            if ($invite->tenant_id !== $tenantId) {
                abort(403, 'Forbidden.');
            }
            if (!in_array(TenantContext::role(), ['owner', 'admin'])) {
                abort(403, 'Only owners and admins can revoke invitations.');
            }
        }

        $invite->update(['status' => 'revoked']);

        return response()->json(['message' => 'Invitation revoked.']);
    }
}
