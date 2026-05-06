<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\TenantInvitationMail;
use App\Mail\TenantInvitationRevokedMail;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use App\Services\InvitationReminderService;
use App\Services\NotificationDispatchService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class TenantUserManagementController extends Controller
{
    public function __construct(
        private PermissionService           $permissionService,
        private NotificationDispatchService $notifications,
        private InvitationReminderService   $reminderService,
    ) {}

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * Resolve the acting user's membership for the given tenant.
     * Aborts 403 if not found or not owner/admin (or manager when allowed).
     */
    private function actingMembership(string $tenantId): TenantMembership
    {
        $userId = Auth::guard('tenant')->id();

        // Super admin: synthesise a virtual owner membership (no DB row needed)
        if (Auth::guard('web')->check()) {
            $m           = new TenantMembership();
            $m->role     = 'owner';
            $m->tenant_id = $tenantId;
            return $m;
        }

        $membership = TenantMembership::where('tenant_user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            abort(403, 'You do not have access to this tenant workspace.');
        }

        return $membership;
    }

    /**
     * Ensure acting user is owner or admin. Abort 403 otherwise.
     */
    private function requireAdminAccess(TenantMembership $acting): void
    {
        if (! in_array($acting->role, ['owner', 'admin'])) {
            abort(403, 'Only Tenant Owners and Admins can perform this action.');
        }
    }

    /**
     * Role hierarchy weight — higher = more privileged.
     */
    private function roleWeight(string $role): int
    {
        return match($role) {
            'owner'   => 100,
            'admin'   => 80,
            'manager' => 60,
            'member'  => 40,
            'viewer'  => 20,
            default   => 0,
        };
    }

    // ── Index ─────────────────────────────────────────────────────

    public function index(string $tenantId)
    {
        $tenant  = Tenant::findOrFail($tenantId);
        $acting  = $this->actingMembership($tenantId);
        $isAdmin = in_array($acting->role, ['owner', 'admin']);

        $memberships = TenantMembership::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'suspended'])
            ->with('tenantUser')
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 WHEN 'manager' THEN 3 WHEN 'member' THEN 4 ELSE 5 END")
            ->get();

        $pendingInvites = TenantInvitation::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        return view('tenant.users.index', compact(
            'tenant',
            'memberships',
            'pendingInvites',
            'isAdmin',
        ) + [
            'permissionService' => $this->permissionService,
            'actingRole'        => $acting->role,
        ]);
    }

    // ── Invite ────────────────────────────────────────────────────

    public function invite(string $tenantId, Request $request)
    {
        $tenant = Tenant::findOrFail($tenantId);
        $acting = $this->actingMembership($tenantId);

        // Only owner/admin can invite; manager can invite members only (enforced below)
        if (! in_array($acting->role, ['owner', 'admin', 'manager'])) {
            abort(403, 'You do not have permission to invite users.');
        }

        $request->validate([
            'email' => ['required', 'email'],
            'role'  => ['required', Rule::in(['admin', 'manager', 'member', 'viewer'])],
        ]);

        $invitedRole = $request->input('role');

        // Cannot invite 'owner'
        if ($invitedRole === 'owner') {
            return back()->withErrors(['role' => 'You cannot invite someone as Owner.'])->withInput();
        }

        // Role hierarchy: cannot invite someone to a role >= your own
        if ($this->roleWeight($invitedRole) >= $this->roleWeight($acting->role)) {
            return back()->withErrors(['role' => 'You cannot invite someone to a role equal to or higher than your own.'])->withInput();
        }

        // Manager can only invite members/viewers
        if ($acting->role === 'manager' && ! in_array($invitedRole, ['member', 'viewer'])) {
            return back()->withErrors(['role' => 'Managers can only invite Members or Viewers.'])->withInput();
        }

        $email = strtolower(trim($request->input('email')));

        // Check for existing active membership by email
        $existingUser = TenantUser::whereRaw('lower(email) = ?', [$email])->first();
        if ($existingUser) {
            $alreadyMember = TenantMembership::where('tenant_id', $tenantId)
                ->where('tenant_user_id', $existingUser->id)
                ->whereIn('status', ['active', 'suspended'])
                ->exists();

            if ($alreadyMember) {
                return back()->withErrors(['email' => 'This user is already a member of this workspace.'])->withInput();
            }
        }

        // Check for existing pending invitation
        $existingInvite = TenantInvitation::where('tenant_id', $tenantId)
            ->where('email', $email)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->exists();

        if ($existingInvite) {
            return back()->withErrors(['email' => 'A pending invitation already exists for this email.'])->withInput();
        }

        $invitedById    = Auth::guard('tenant')->id();
        $permPreset     = $request->input('permissions_preset');

        $invitation = TenantInvitation::create([
            'tenant_id'          => $tenantId,
            'email'              => $email,
            'role'               => $invitedRole,
            'status'             => 'pending',
            'invited_by'         => $invitedById,
            'expires_at'         => now()->addDays(7),
            'permissions_preset' => $permPreset ?: null,
        ]);

        // Send initial invitation email
        try {
            Mail::send(new TenantInvitationMail($invitation));
        } catch (\Throwable) {
            // queue failure — don't block UI
        }

        // Initialise reminder schedule
        $this->reminderService->initialiseSchedule($invitation);

        // Notify all tenant admins
        $this->notifications->dispatchToTenantAdmins(
            tenantId:    $tenantId,
            category:    'team',
            priority:    'normal',
            title:       'New Team Invitation Sent',
            body:        "An invitation was sent to {$email} for the role of " . ucfirst($invitedRole) . ".",
            actionUrl:   route('tenant.users', $tenantId),
            actionLabel: 'View Users',
            dedupeSuffix: "invite:{$invitation->id}",
            metadata:    ['invitation_id' => $invitation->id, 'email' => $email, 'role' => $invitedRole],
        );

        return back()->with('success', "Invitation sent to {$email}.");
    }

    // ── Resend Invite ─────────────────────────────────────────────

    public function resendInvite(string $tenantId, string $inviteId)
    {
        $acting = $this->actingMembership($tenantId);
        $this->requireAdminAccess($acting);

        $invitation = TenantInvitation::where('id', $inviteId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->firstOrFail();

        // Rate-limited manual resend (24h window)
        if (! $this->reminderService->manualResend($invitation)) {
            return back()->withErrors(['resend' => 'You can only resend this invitation once every 24 hours.']);
        }

        // Send the invitation email again
        try {
            Mail::send(new TenantInvitationMail($invitation));
        } catch (\Throwable) {
            // silent — rate limit already updated
        }

        $this->notifications->dispatchToTenantAdmins(
            tenantId:    $tenantId,
            category:    'team',
            priority:    'low',
            title:       'Invitation Resent',
            body:        "Invitation to {$invitation->email} was resent.",
            actionUrl:   route('tenant.users', $tenantId),
            actionLabel: 'View Users',
            dedupeSuffix: "resend:{$invitation->id}:" . now()->format('YmdHi'),
        );

        return back()->with('success', "Invitation resent to {$invitation->email}.");
    }

    // ── Revoke Invite ─────────────────────────────────────────────

    public function revokeInvite(string $tenantId, string $inviteId)
    {
        $acting = $this->actingMembership($tenantId);
        $this->requireAdminAccess($acting);

        $invitation = TenantInvitation::with('tenant')->where('id', $inviteId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->firstOrFail();

        $invitation->status = 'revoked';
        $invitation->save();

        // Suppress all future reminders
        $this->reminderService->suppress($invitation, 'revoked');

        // Notify the invitee that their invitation was cancelled
        try {
            Mail::send(new TenantInvitationRevokedMail($invitation));
        } catch (\Throwable) {
            // silent
        }

        return back()->with('success', "Invitation for {$invitation->email} has been revoked.");
    }

    // ── Update Permissions ────────────────────────────────────────

    public function updatePermissions(string $tenantId, string $userId, Request $request)
    {
        $acting = $this->actingMembership($tenantId);
        $this->requireAdminAccess($acting);

        $membership = TenantMembership::where('tenant_user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->firstOrFail();

        // Only manager permissions can be customised
        if ($membership->role !== 'manager') {
            return back()->withErrors(['permissions' => 'Custom permissions are only supported for the Manager role.']);
        }

        $incoming = $request->validate([
            'permissions'   => ['required', 'array'],
            'permissions.*' => ['boolean'],
        ]);

        $this->permissionService->updateManagerPermissions($membership, $incoming['permissions']);

        return back()->with('success', 'Permissions updated successfully.');
    }

    // ── Toggle Billing ────────────────────────────────────────────

    public function toggleBilling(string $tenantId, string $userId, Request $request)
    {
        $acting = $this->actingMembership($tenantId);
        $this->requireAdminAccess($acting);

        $membership = TenantMembership::where('tenant_user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->firstOrFail();

        if ($membership->role !== 'manager') {
            return back()->withErrors(['billing' => 'Billing access can only be toggled for Managers.']);
        }

        $enable = $request->boolean('enable');
        $this->permissionService->updateManagerPermissions($membership, [
            'manage_billing_and_subscription' => $enable,
        ]);

        $label = $enable ? 'enabled' : 'disabled';
        return back()->with('success', "Billing access {$label} for this manager.");
    }

    // ── Deactivate User ───────────────────────────────────────────

    public function deactivateUser(string $tenantId, string $userId)
    {
        $acting = $this->actingMembership($tenantId);
        $this->requireAdminAccess($acting);

        $membership = TenantMembership::where('tenant_user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Cannot suspend the owner
        if ($membership->role === 'owner') {
            abort(403, 'The Tenant Owner cannot be deactivated.');
        }

        // Cannot suspend someone with a higher or equal role
        if ($this->roleWeight($membership->role) >= $this->roleWeight($acting->role)) {
            abort(403, 'You cannot deactivate a user with an equal or higher role.');
        }

        $membership->status = 'suspended';
        $membership->save();

        return back()->with('success', 'User has been deactivated.');
    }

    // ── Remove User ───────────────────────────────────────────────

    public function removeUser(string $tenantId, string $userId)
    {
        $acting = $this->actingMembership($tenantId);
        $this->requireAdminAccess($acting);

        $membership = TenantMembership::where('tenant_user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Cannot remove the owner
        if ($membership->role === 'owner') {
            abort(403, 'The Tenant Owner cannot be removed.');
        }

        // Cannot remove someone with a higher or equal role
        if ($this->roleWeight($membership->role) >= $this->roleWeight($acting->role)) {
            abort(403, 'You cannot remove a user with an equal or higher role.');
        }

        $membership->status = 'removed';
        $membership->save();

        return back()->with('success', 'User has been removed from this workspace.');
    }

    // ── Send Reminder Now (manual, rate-limited) ──────────────────

    public function sendReminderNow(string $tenantId, string $inviteId)
    {
        $acting = $this->actingMembership($tenantId);
        $this->requireAdminAccess($acting);

        $invitation = TenantInvitation::with('tenant')->where('id', $inviteId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        // 24h rate limit
        if ($invitation->last_manual_resend_at &&
            now()->lt($invitation->last_manual_resend_at->addHours(24))) {
            $nextAllowed = $invitation->last_manual_resend_at->addHours(24)->diffForHumans();
            return back()->withErrors(['reminder' => "You can send the next manual reminder {$nextAllowed}."]);
        }

        // Send invitee reminder email immediately
        $sent = $this->reminderService->sendInviteeReminder($invitation);

        // Update rate limit timestamp regardless (prevents rapid retries on mail failure)
        $invitation->last_manual_resend_at = now();
        $invitation->save();

        if ($sent) {
            return back()->with('success', "Reminder sent to {$invitation->email}.");
        }

        return back()->withErrors(['reminder' => 'Could not send the reminder at this time. Please try again later.']);
    }
}
