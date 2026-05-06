<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\TenantInvitationAcceptedMail;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use App\Services\InvitationReminderService;
use App\Services\NotificationDispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TenantInvitationController extends Controller
{
    public function __construct(
        private NotificationDispatchService $notifications,
        private InvitationReminderService   $reminderService,
    ) {}

    // ── Show accept page ──────────────────────────────────────────

    public function show(string $token)
    {
        $invitation = TenantInvitation::with('tenant')
            ->where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (! $invitation || ! $invitation->isValid()) {
            return view('auth.tenant-invite-expired', compact('invitation'));
        }

        // Check if a TenantUser with this email already exists
        $existingUser = TenantUser::whereRaw('lower(email) = ?', [strtolower($invitation->email)])->first();

        return view('auth.tenant-accept-invite', compact('invitation', 'existingUser'));
    }

    // ── Accept invitation ─────────────────────────────────────────

    public function accept(string $token, Request $request)
    {
        $invitation = TenantInvitation::with('tenant')
            ->where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (! $invitation || ! $invitation->isValid()) {
            return redirect()->route('tenant.login')
                ->withErrors(['email' => 'This invitation link is invalid or has expired.']);
        }

        $email = strtolower($invitation->email);

        // Check for existing TenantUser by email
        $user = TenantUser::whereRaw('lower(email) = ?', [$email])->first();

        if ($user) {
            // Existing user — just add membership and log them in
            if ($user->status !== 'active') {
                return redirect()->route('tenant.login')
                    ->withErrors(['email' => 'Your account is currently suspended.']);
            }
        } else {
            // New user — validate password fields
            $request->validate([
                'first_name' => ['required', 'string', 'max:100'],
                'last_name'  => ['required', 'string', 'max:100'],
                'password'   => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            $user = TenantUser::create([
                'id'         => (string) Str::uuid(),
                'first_name' => trim($request->input('first_name')),
                'last_name'  => trim($request->input('last_name')),
                'email'      => $email,
                'password'   => Hash::make($request->input('password')),
                'status'     => 'active',
            ]);
        }

        // Prevent duplicate membership
        $alreadyMember = TenantMembership::where('tenant_id', $invitation->tenant_id)
            ->where('tenant_user_id', $user->id)
            ->whereIn('status', ['active', 'suspended'])
            ->exists();

        if (! $alreadyMember) {
            TenantMembership::create([
                'tenant_id'                 => $invitation->tenant_id,
                'tenant_user_id'            => $user->id,
                'role'                      => $invitation->role,
                'status'                    => 'active',
                'joined_by_invitation'      => true,
                'joined_at'                 => now(),
                'invited_by_user_id'        => $invitation->invited_by,
                'password_review_completed' => true, // no password review step exists; skip on all future logins
                'setup_completed'           => true,
            ]);
        }

        // Mark invitation as accepted and suppress further reminders
        $invitation->status      = 'accepted';
        $invitation->accepted_at = now();
        $invitation->save();

        $this->reminderService->suppress($invitation, 'accepted');

        // Email the inviter (if they exist and are different from acceptee)
        $inviter = $invitation->invitedBy;
        if ($inviter && $inviter->id !== $user->id) {
            try {
                Mail::send(new TenantInvitationAcceptedMail($invitation, $inviter, $user));
            } catch (\Throwable) {
                // silent
            }
        }

        // Notify tenant admins
        $this->notifications->dispatchToTenantAdmins(
            tenantId:    $invitation->tenant_id,
            category:    'team',
            priority:    'normal',
            title:       'Invitation Accepted',
            body:        "{$user->first_name} {$user->last_name} ({$email}) joined as " . ucfirst($invitation->role) . ".",
            actionUrl:   route('tenant.users', $invitation->tenant_id),
            actionLabel: 'View Users',
            dedupeSuffix: "accepted:{$invitation->id}",
            metadata:    [
                'invitation_id' => $invitation->id,
                'user_id'       => $user->id,
                'role'          => $invitation->role,
            ],
        );

        // Log the user in
        Auth::guard('tenant')->login($user);
        $request->session()->regenerate();

        // Check for multiple active memberships
        $activeMemberships = TenantMembership::where('tenant_user_id', $user->id)
            ->where('status', 'active')
            ->with('tenant')
            ->get();

        $tenantName = $invitation->tenant?->name ?? 'your workspace';
        $roleLabel  = ucfirst($invitation->role);

        if ($activeMemberships->count() > 1) {
            session(['pending_tenant_select' => $activeMemberships->pluck('tenant_id')->toArray()]);
            return redirect()->route('tenant.select-workspace')
                ->with('success', "Welcome! You've joined {$tenantName} as {$roleLabel}. Select a workspace to continue.");
        }

        return redirect()->route('tenant.dashboard', $invitation->tenant_id)
            ->with('success', "Welcome to {$tenantName}! You've joined as {$roleLabel}. R Bunny will guide you through your first steps.");
    }
}
