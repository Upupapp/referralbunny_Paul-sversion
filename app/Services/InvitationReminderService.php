<?php

namespace App\Services;

use App\Mail\TenantInvitationReminderMail;
use App\Mail\TenantInvitationExpiredMail;
use App\Mail\TenantInviterReminderMail;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use Illuminate\Support\Facades\Mail;

/**
 * Processes pending invitation reminders on a schedule.
 *
 * Invitee schedule (7-day invite):
 *   Reminder 1 — 24h after invite sent
 *   Reminder 2 — 3 days after invite sent
 *   Reminder 3 — 24h before expiry (last chance)
 *
 * Inviter in-app reminders:
 *   Reminder 1 — 48h after invite sent (in-app only)
 *   Reminder 2 — 5 days after invite sent (in-app + email)
 */
class InvitationReminderService
{
    private const MAX_INVITEE_REMINDERS = 3;
    private const MAX_INVITER_REMINDERS = 2;

    // Invitee reminder schedule (hours after invitation sent)
    private const INVITEE_SCHEDULE = [24, 72, null]; // null = 24h before expiry for reminder 3

    // Inviter reminder schedule (hours after invitation sent)
    private const INVITER_SCHEDULE = [48, 120];

    public function __construct(
        private NotificationDispatchService $notifications,
    ) {}

    /**
     * Process all due invitee reminders.
     * Called by the scheduled command.
     */
    public function processInviteeReminders(): int
    {
        $sent = 0;

        $invitations = TenantInvitation::with(['tenant', 'invitedBy'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->whereNull('reminder_suppressed_at')
            ->where(function ($q) {
                $q->where('next_reminder_at', '<=', now())
                  ->whereNotNull('next_reminder_at');
            })
            ->where('reminder_count', '<', self::MAX_INVITEE_REMINDERS)
            ->get();

        foreach ($invitations as $invitation) {
            if ($this->sendInviteeReminder($invitation)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Process all due inviter reminders.
     * Called by the scheduled command.
     */
    public function processInviterReminders(): int
    {
        $sent = 0;

        $invitations = TenantInvitation::with(['tenant', 'invitedBy'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->whereNull('reminder_suppressed_at')
            ->where(function ($q) {
                $q->where('next_inviter_reminder_at', '<=', now())
                  ->whereNotNull('next_inviter_reminder_at');
            })
            ->where('inviter_reminder_count', '<', self::MAX_INVITER_REMINDERS)
            ->whereNotNull('invited_by')
            ->get();

        foreach ($invitations as $invitation) {
            if ($this->sendInviterReminder($invitation)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send a reminder to the invitee and schedule the next one.
     */
    public function sendInviteeReminder(TenantInvitation $invitation): bool
    {
        if ($invitation->status !== 'pending' || now()->gte($invitation->expires_at)) {
            return false;
        }

        // Update DB counter BEFORE queuing so that if Mail::queue fails the counter
        // still advances — preventing the scheduler from immediately re-queuing the
        // same reminder on the next tick (which would cause duplicate delivery on retry).
        $newCount = $invitation->reminder_count + 1;
        $next     = $this->nextInviteeReminderAt($invitation, $newCount);

        $invitation->update([
            'reminder_count'         => $newCount,
            'last_reminder_sent_at'  => now(),
            'next_reminder_at'       => $next,
        ]);

        try {
            Mail::queue(new TenantInvitationReminderMail($invitation));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[InvitationReminderService] Failed to queue invitee reminder', [
                'invitation_id' => $invitation->id,
                'error'         => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Send an in-app + optional email reminder to the inviter.
     */
    public function sendInviterReminder(TenantInvitation $invitation): bool
    {
        if ($invitation->status !== 'pending' || now()->gte($invitation->expires_at)) {
            return false;
        }

        $inviter = $invitation->invitedBy;
        if (! $inviter) {
            return false;
        }

        $newCount = $invitation->inviter_reminder_count + 1;

        // In-app notification — always
        $this->notifications->dispatch(
            category:         'tenant_workspace',
            priority:         $newCount >= self::MAX_INVITER_REMINDERS ? 'high' : 'normal',
            title:            'Pending invitation — no response yet',
            body:             "{$invitation->email} still hasn't accepted your invitation to join as " . ucfirst($invitation->role) . ". Expires " . $invitation->expires_at->diffForHumans() . ".",
            notifiableType:   'tenant_admin',
            notifiableId:     (string) $inviter->id,
            tenantId:         $invitation->tenant_id,
            actionUrl:        url("/tenant/{$invitation->tenant_id}/users"),
            actionLabel:      'View Invitations',
            deduplicationKey: "invite-inviter-reminder:{$invitation->id}:pass{$newCount}",
            metadata:         ['invitation_id' => $invitation->id],
        );

        // DB update first — same safe-ordering rationale as sendInviteeReminder()
        $nextAt = $this->nextInviterReminderAt($invitation, $newCount);

        $invitation->update([
            'inviter_reminder_count'   => $newCount,
            'last_inviter_reminder_at' => now(),
            'next_inviter_reminder_at' => $nextAt,
        ]);

        // Email on second inviter reminder (day 5) only
        if ($newCount >= 2) {
            try {
                Mail::queue(new TenantInviterReminderMail($invitation, $inviter));
            } catch (\Throwable) {
                // silent — in-app notification already fired above
            }
        }

        return true;
    }

    /**
     * Initialise reminder schedule right after invitation is created.
     * Sets next_reminder_at and next_inviter_reminder_at.
     */
    public function initialiseSchedule(TenantInvitation $invitation): void
    {
        $invitation->update([
            'initial_email_sent_at'    => now(),
            'next_reminder_at'         => now()->addHours(self::INVITEE_SCHEDULE[0]),
            'next_inviter_reminder_at' => now()->addHours(self::INVITER_SCHEDULE[0]),
        ]);
    }

    /**
     * Suppress all future reminders for an invitation.
     */
    public function suppress(TenantInvitation $invitation, string $reason = 'manual'): void
    {
        $invitation->update([
            'reminder_suppressed_at'     => now(),
            'reminder_suppressed_reason' => $reason,
            'next_reminder_at'           => null,
            'next_inviter_reminder_at'   => null,
        ]);
    }

    /**
     * Handle manual resend — resets invitee reminder schedule from now,
     * respecting the 24h rate limit.
     *
     * Returns true on success, false if rate-limited.
     */
    public function manualResend(TenantInvitation $invitation): bool
    {
        if ($invitation->last_manual_resend_at &&
            now()->lt($invitation->last_manual_resend_at->addHours(24))) {
            return false;
        }

        // Extend expiry 7 days from now
        $invitation->expires_at = now()->addDays(7);

        // Reset invitee reminder schedule
        $invitation->reminder_count        = 0;
        $invitation->last_reminder_sent_at = null;
        $invitation->next_reminder_at      = now()->addHours(self::INVITEE_SCHEDULE[0]);
        $invitation->last_manual_resend_at = now();

        // Unsuppress if previously suppressed
        if ($invitation->reminder_suppressed_at) {
            $invitation->reminder_suppressed_at     = null;
            $invitation->reminder_suppressed_reason = null;
        }

        $invitation->save();

        return true;
    }

    /**
     * Calculate when to send the next invitee reminder.
     * Returns null when no more reminders should be sent.
     */
    private function nextInviteeReminderAt(TenantInvitation $invitation, int $sentCount): ?\Carbon\Carbon
    {
        if ($sentCount >= self::MAX_INVITEE_REMINDERS) {
            return null;
        }

        // Reminder 3 = 24h before expiry
        if ($sentCount === 2) {
            $candidate = $invitation->expires_at->subHours(24);
            return $candidate->gt(now()) ? $candidate : null;
        }

        $hoursOffset = self::INVITEE_SCHEDULE[$sentCount] ?? null;
        if ($hoursOffset === null) {
            return null;
        }

        // Schedule from original created_at
        $candidate = $invitation->created_at->addHours($hoursOffset);
        return $candidate->gt(now()) ? $candidate : now()->addMinutes(30);
    }

    /**
     * Calculate when to send the next inviter reminder.
     * Returns null when no more reminders should be sent.
     */
    private function nextInviterReminderAt(TenantInvitation $invitation, int $sentCount): ?\Carbon\Carbon
    {
        if ($sentCount >= self::MAX_INVITER_REMINDERS) {
            return null;
        }

        $hoursOffset = self::INVITER_SCHEDULE[$sentCount] ?? null;
        if ($hoursOffset === null) {
            return null;
        }

        $candidate = $invitation->created_at->addHours($hoursOffset);
        return $candidate->gt(now()) ? $candidate : now()->addMinutes(30);
    }
}
