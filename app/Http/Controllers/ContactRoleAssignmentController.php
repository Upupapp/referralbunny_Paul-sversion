<?php

namespace App\Http\Controllers;

use App\Mail\ContactRoleInvitationMail;
use App\Models\ActivityLog;
use App\Models\ContactRoleInvitation;
use App\Models\DealPartner;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Partner;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\TenantContext;
use App\Services\TenantPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\EmailLogger;
use Illuminate\Support\Str;

class ContactRoleAssignmentController extends Controller
{
    // ── List role invitations for a contact ───────────────────────────────

    public function index(Request $request, string $contactId): JsonResponse
    {
        $tenantId = TenantContext::requireId();

        // Ensure contact belongs to this tenant
        $contact = DB::table('contacts')
            ->where('id', $contactId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$contact) {
            abort(404, 'Contact not found.');
        }

        $invitations = ContactRoleInvitation::where('contact_id', $contactId)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($invitations);
    }

    // ── Assign a role to a contact (send invitation) ─────────────────────

    public function store(Request $request): JsonResponse
    {
        $tenantId = TenantContext::requireId();

        $data = $request->validate([
            'contact_id'         => 'required|string',
            'role'               => 'required|in:referrer,tenant_manager,tenant_staff,partner',
            'associated_deal_id' => 'nullable|string',
            'message'            => 'nullable|string|max:500',
            'permissions_preset' => 'nullable|string',
        ]);

        // ── 1. Load and scope contact to this tenant ─────────────────────
        $contact = DB::table('contacts')
            ->where('id', $data['contact_id'])
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$contact) {
            return response()->json(['error' => 'Contact not found in this tenant.'], 404);
        }

        // ── 2. Determine caller identity and enforce permissions ──────────
        [$actorUserId, $actorRole] = $this->resolveActor();

        $permCheck = $this->checkPermission($actorRole, $data['role'], $tenantId, $data['associated_deal_id'] ?? null, $actorUserId);
        if ($permCheck !== true) {
            $this->auditLog($tenantId, $contact->id, null, $data['role'], $data['associated_deal_id'] ?? null, $actorUserId, $actorRole, 'blocked_by_permission', $permCheck);
            return response()->json(['error' => $permCheck], 403);
        }

        // ── 3. Partner role requires a deal ──────────────────────────────
        if ($data['role'] === 'partner' && empty($data['associated_deal_id'])) {
            return response()->json(['error' => 'A deal must be selected when assigning the Partner role.'], 422);
        }

        // ── 4. Verify deal belongs to tenant (if provided) ───────────────
        $deal = null;
        if (!empty($data['associated_deal_id'])) {
            $deal = Lead::where('id', $data['associated_deal_id'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$deal) {
                return response()->json(['error' => 'Deal not found in this tenant.'], 404);
            }

            // Referrer can only assign partner to their own deals
            if ($actorRole === 'referrer') {
                $reseller = Auth::guard('reseller')->user();
                if (!$reseller || $deal->reseller_name !== $reseller->name) {
                    return response()->json(['error' => 'You can only add Partners to your own deals.'], 403);
                }
            }
        }

        // ── 5. Email is required to send invitation ───────────────────────
        if (empty($contact->email)) {
            return response()->json([
                'error' => 'This contact needs an email address before an invitation can be sent.',
                'needs_email' => true,
            ], 422);
        }

        // ── 6. Plan limit check ───────────────────────────────────────────
        $planService = app(TenantPlanService::class);

        if ($data['role'] === 'referrer') {
            $limitCheck = $planService->canInviteReferrer($tenantId);
            if (!$limitCheck['allowed']) {
                $this->auditLog($tenantId, $contact->id, null, $data['role'], $data['associated_deal_id'] ?? null, $actorUserId, $actorRole, 'blocked_by_plan', $limitCheck['reason']);
                return response()->json(['error' => $limitCheck['reason'], 'blocked_by_plan' => true], 403);
            }
        }

        if (in_array($data['role'], ['tenant_manager', 'tenant_staff'])) {
            $limitCheck = $planService->canInviteTenantUser($tenantId);
            if (!$limitCheck['allowed']) {
                $this->auditLog($tenantId, $contact->id, null, $data['role'], $data['associated_deal_id'] ?? null, $actorUserId, $actorRole, 'blocked_by_plan', $limitCheck['reason']);
                return response()->json(['error' => $limitCheck['reason'], 'blocked_by_plan' => true], 403);
            }
        }

        // ── 7. Duplicate detection ────────────────────────────────────────
        $existingQuery = ContactRoleInvitation::where('contact_id', $contact->id)
            ->where('tenant_id', $tenantId)
            ->where('invited_role', $data['role'])
            ->whereIn('status', ['pending', 'accepted']);

        if ($data['role'] === 'partner' && !empty($data['associated_deal_id'])) {
            $existingQuery->where('associated_deal_id', $data['associated_deal_id']);
        }

        $existing = $existingQuery->first();

        if ($existing) {
            $label = $existing->roleLabel();
            $msg   = $existing->status === 'accepted'
                ? "This contact is already an active {$label} in this tenant."
                : "A pending {$label} invitation already exists for this contact.";

            return response()->json([
                'error'       => $msg,
                'duplicate'   => true,
                'invitation'  => $existing,
            ], 409);
        }

        // If email matches existing Reseller for this tenant (role = referrer)
        if ($data['role'] === 'referrer') {
            $existingReseller = Reseller::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(email) = ?', [strtolower($contact->email)])
                ->whereIn('status', ['active', 'nda_signed', 'invited'])
                ->first();

            if ($existingReseller) {
                return response()->json([
                    'error'     => 'This email already has a Referrer account for this tenant.',
                    'duplicate' => true,
                ], 409);
            }
        }

        // ── 8. Create invitation record ────────────────────────────────────
        $token     = Str::random(64);
        $expiresAt = now()->addDays(7);

        $invitation = ContactRoleInvitation::create([
            'id'                  => (string) Str::uuid(),
            'tenant_id'           => $tenantId,
            'contact_id'          => $contact->id,
            'invited_email'       => strtolower($contact->email),
            'invited_role'        => $data['role'],
            'invited_by_user_id'  => $actorUserId,
            'invited_by_role'     => $actorRole,
            'status'              => 'pending',
            'token'               => $token,
            'expires_at'          => $expiresAt,
            'associated_deal_id'  => $data['associated_deal_id'] ?? null,
            'message'             => $data['message'] ?? null,
            'permissions_json'    => $data['permissions_preset'] ? ['preset' => $data['permissions_preset']] : null,
        ]);

        // ── 8b. For partner role: ensure partner_users + deal_partners rows exist ──
        if ($data['role'] === 'partner') {
            $partner = Partner::where('tenant_id', $tenantId)
                ->where('email', strtolower($contact->email))
                ->first();

            if (!$partner) {
                $partner = Partner::create([
                    'id'              => (string) Str::uuid(),
                    'tenant_id'       => $tenantId,
                    'email'           => strtolower($contact->email),
                    'setup_token'     => $token,
                    'status'          => 'invited',
                    'first_name'      => $contact->first_name ?? null,
                    'last_name'       => $contact->last_name ?? null,
                    'invited_by_type' => $actorRole === 'referrer' ? 'App\\Models\\Reseller' : 'App\\Models\\TenantUser',
                    'invited_by_id'   => $actorUserId,
                ]);
            } else {
                // Refresh setup token so the new email link works
                $partner->update(['setup_token' => $token]);
            }

            $dealPartnerExists = DealPartner::where('deal_id', $data['associated_deal_id'])
                ->where('partner_user_id', $partner->id)
                ->exists();

            if (!$dealPartnerExists) {
                DealPartner::create([
                    'id'              => (string) Str::uuid(),
                    'tenant_id'       => $tenantId,
                    'deal_id'         => $data['associated_deal_id'],
                    'partner_user_id' => $partner->id,
                    'added_by_id'     => $actorUserId,
                    'added_by_type'   => $actorRole === 'referrer' ? 'App\\Models\\Reseller' : 'App\\Models\\TenantUser',
                    'status'          => 'invited',
                    'invited_at'      => now(),
                ]);
            }
        }

        // ── 9. Send invitation email ───────────────────────────────────────
        $tenant      = Tenant::find($tenantId);
        $contactName = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) ?: $contact->email;
        $dealName    = $deal ? ($deal->name ?? null) : null;

        EmailLogger::send(
            mailable:       new ContactRoleInvitationMail($invitation, $contactName, $tenant?->name ?? 'the platform', $dealName),
            recipientEmail: $contact->email,
            recipientType:  'external',
            emailKey:       'contact_role_invite.' . $invitation->id,
            subject:        "You've been invited to join {$tenant?->name ?? 'the platform'}",
            tenantId:       $tenantId,
        );

        // ── 10. Audit log ──────────────────────────────────────────────────
        $this->auditLog(
            $tenantId, $contact->id, null, $data['role'],
            $data['associated_deal_id'] ?? null, $actorUserId, $actorRole,
            'invitation_sent', null
        );

        // ── 11. Notify tenant admin (if actor is a referrer) ──────────────
        if ($actorRole === 'referrer' && $data['role'] === 'partner') {
            $this->notifyTenantAdmin($tenantId, $contactName, 'partner', $deal);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Invitation sent to ' . $contact->email,
            'invitation' => $invitation,
        ], 201);
    }

    // ── Revoke a pending invitation ────────────────────────────────────────

    public function destroy(string $id): JsonResponse
    {
        $tenantId = TenantContext::requireId();

        $invitation = ContactRoleInvitation::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if ($invitation->status !== 'pending') {
            return response()->json(['error' => 'Only pending invitations can be revoked.'], 422);
        }

        [$actorUserId, $actorRole] = $this->resolveActor();

        // Only tenant admin/manager/owner or the original inviter (referrer) can revoke
        if ($actorRole === 'referrer' && $invitation->invited_by_user_id !== $actorUserId) {
            return response()->json(['error' => 'You can only revoke invitations you sent.'], 403);
        }

        $invitation->update([
            'status'              => 'revoked',
            'revoked_at'          => now(),
            'revoked_by_user_id'  => $actorUserId,
        ]);

        $this->auditLog(
            $tenantId, $invitation->contact_id, null, $invitation->invited_role,
            $invitation->associated_deal_id, $actorUserId, $actorRole,
            'invitation_revoked', null
        );

        return response()->json(['success' => true, 'message' => 'Invitation revoked.']);
    }

    // ── Resend a pending invitation ────────────────────────────────────────

    public function resend(string $id): JsonResponse
    {
        $tenantId = TenantContext::requireId();

        $invitation = ContactRoleInvitation::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if ($invitation->status !== 'pending') {
            return response()->json(['error' => 'Only pending invitations can be resent.'], 422);
        }

        // Extend expiry
        $invitation->update([
            'expires_at'            => now()->addDays(7),
            'reminder_count'        => $invitation->reminder_count + 1,
            'last_reminder_sent_at' => now(),
        ]);

        $contact = DB::table('contacts')->where('id', $invitation->contact_id)->first();
        $tenant  = Tenant::find($tenantId);
        $deal    = $invitation->associated_deal_id ? Lead::find($invitation->associated_deal_id) : null;

        $contactName = $contact ? trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) : $invitation->invited_email;

        EmailLogger::send(
            mailable:       new ContactRoleInvitationMail($invitation, $contactName, $tenant?->name ?? 'the platform', $deal?->name),
            recipientEmail: $invitation->invited_email,
            recipientType:  'external',
            emailKey:       'contact_role_invite_resend.' . $invitation->id,
            dailyDedup:     true,
            subject:        "Reminder: You've been invited to join {$tenant?->name ?? 'the platform'}",
            tenantId:       $tenantId,
        );

        [$actorUserId, $actorRole] = $this->resolveActor();
        $this->auditLog(
            $tenantId, $invitation->contact_id, null, $invitation->invited_role,
            $invitation->associated_deal_id, $actorUserId, $actorRole,
            'invitation_resent', null
        );

        return response()->json(['success' => true, 'message' => 'Invitation resent.']);
    }

    // ── Accept invitation (API endpoint for non-referrer/partner roles) ───

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = ContactRoleInvitation::where('token', $token)->firstOrFail();

        if (!$invitation->isValid()) {
            return response()->json(['error' => 'This invitation is no longer valid.'], 410);
        }

        // For referrer and partner, acceptance is handled by their own auth controllers
        if ($invitation->invited_role === 'referrer') {
            return response()->json(['redirect' => url('/reseller/setup?token=' . $token)]);
        }
        if ($invitation->invited_role === 'partner') {
            return response()->json(['redirect' => url('/partner/setup?token=' . $token)]);
        }

        // For tenant_manager and tenant_staff, delegate to existing TenantInvitation flow
        // This endpoint validates and returns the invitation data for the acceptance UI
        return response()->json([
            'invitation'   => $invitation,
            'contact'      => DB::table('contacts')->where('id', $invitation->contact_id)->first(),
            'tenant'       => Tenant::find($invitation->tenant_id),
            'role_label'   => $invitation->roleLabel(),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function resolveActor(): array
    {
        if (Auth::guard('tenant')->check()) {
            $user = Auth::guard('tenant')->user();
            return [$user->id, 'manager'];
        }
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            return [$user->id, 'owner'];
        }
        if (Auth::guard('reseller')->check()) {
            $reseller = Auth::guard('reseller')->user();
            return [$reseller->id, 'referrer'];
        }
        return ['unknown', 'unknown'];
    }

    private function checkPermission(string $actorRole, string $targetRole, string $tenantId, ?string $dealId, string $actorUserId): bool|string
    {
        // Partner cannot assign roles
        if ($actorRole === 'partner') {
            return 'Partners cannot assign roles.';
        }

        // Referrer can only assign Partner role (with deal)
        if ($actorRole === 'referrer') {
            if ($targetRole !== 'partner') {
                return 'You do not have permission to assign the ' . ucfirst(str_replace('_', ' ', $targetRole)) . ' role.';
            }
            if (empty($dealId)) {
                return 'You must select a deal to assign the Partner role.';
            }
            return true;
        }

        // Tenant owner/admin/manager can assign all tenant roles
        if (in_array($actorRole, ['owner', 'admin', 'manager'])) {
            return true;
        }

        return 'You do not have permission to assign roles.';
    }

    private function auditLog(
        string $tenantId, string $contactId, ?string $targetUserId,
        string $role, ?string $dealId, string $actorUserId, string $actorRole,
        string $event, ?string $reason
    ): void {
        try {
            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id'   => $actorUserId,
                'action'    => 'contact_role_' . $event,
                'entity'    => 'contact',
                'entity_id' => $contactId,
                'metadata'  => json_encode([
                    'assigned_role'      => $role,
                    'associated_deal_id' => $dealId,
                    'actor_role'         => $actorRole,
                    'target_user_id'     => $targetUserId,
                    'reason'             => $reason,
                    'timestamp'          => now()->toIso8601String(),
                ]),
            ]);
        } catch (\Throwable) {
            // Never crash on audit failure
        }
    }

    private function notifyTenantAdmin(string $tenantId, string $contactName, string $role, ?Lead $deal): void
    {
        try {
            $adminMembers = DB::table('tenant_memberships')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('role', ['owner', 'admin'])
                ->pluck('tenant_user_id');

            foreach ($adminMembers as $userId) {
                Notification::create([
                    'id'              => (string) Str::uuid(),
                    'tenant_id'       => $tenantId,
                    'notifiable_type' => 'tenant_admin',
                    'notifiable_id'   => $userId,
                    'category'        => 'reseller_referrer',
                    'type'            => 'info',
                    'priority'        => 'normal',
                    'title'           => 'New Partner Invitation Sent',
                    'message'         => "{$contactName} was invited as a Partner" . ($deal ? " for deal \"{$deal->name}\"" : '') . " by a Referrer.",
                    'is_read'         => false,
                    'is_dismissed'    => false,
                    'sent_at'         => now(),
                ]);
            }
        } catch (\Throwable) {}
    }
}
