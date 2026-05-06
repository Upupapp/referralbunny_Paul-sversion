<?php

namespace App\Mail;

use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInvitationReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $acceptUrl;
    public string $tenantName;
    public string $inviterName;
    public string $roleLabel;
    public string $expiresAt;
    public int    $reminderNumber;
    public bool   $isLastReminder;

    public function __construct(public TenantInvitation $invitation)
    {
        $this->acceptUrl      = url("/tenant/accept-invite/{$invitation->token}");
        $this->tenantName     = $invitation->tenant?->name ?? 'the workspace';
        $this->roleLabel      = ucfirst($invitation->role);
        $this->expiresAt      = $invitation->expires_at->format('F j, Y');
        $this->reminderNumber = $invitation->reminder_count + 1;
        $this->isLastReminder = $this->reminderNumber >= 3;

        $inviter          = $invitation->invitedBy;
        $this->inviterName = $inviter
            ? trim("{$inviter->first_name} {$inviter->last_name}")
            : $this->tenantName;
    }

    public function envelope(): Envelope
    {
        $urgency = $this->isLastReminder ? 'Last chance — ' : 'Reminder: ';
        return new Envelope(
            to:      [new Address($this->invitation->email)],
            subject: "{$urgency}Your invitation to join {$this->tenantName} expires on {$this->expiresAt}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-invitation-reminder',
            with: [
                'acceptUrl'      => $this->acceptUrl,
                'tenantName'     => $this->tenantName,
                'inviterName'    => $this->inviterName,
                'roleLabel'      => $this->roleLabel,
                'expiresAt'      => $this->expiresAt,
                'reminderNumber' => $this->reminderNumber,
                'isLastReminder' => $this->isLastReminder,
            ],
        );
    }
}
