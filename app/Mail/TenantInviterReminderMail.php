<?php

namespace App\Mail;

use App\Models\TenantInvitation;
use App\Models\TenantUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInviterReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $inviterName;
    public string $inviterEmail;
    public string $tenantName;
    public string $inviteeEmail;
    public string $roleLabel;
    public string $expiresAt;
    public string $usersUrl;
    public int    $inviterReminderNumber;

    public function __construct(TenantInvitation $invitation, TenantUser $inviter)
    {
        $this->inviterName          = trim("{$inviter->first_name} {$inviter->last_name}");
        $this->inviterEmail         = $inviter->email;
        $this->tenantName           = $invitation->tenant?->name ?? 'the workspace';
        $this->inviteeEmail         = $invitation->email;
        $this->roleLabel            = ucfirst($invitation->role);
        $this->expiresAt            = $invitation->expires_at->format('F j, Y');
        $this->usersUrl             = url("/tenant/{$invitation->tenant_id}/users");
        $this->inviterReminderNumber = $invitation->inviter_reminder_count + 1;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Heads up: {$this->inviteeEmail} hasn't accepted your invitation yet",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-inviter-reminder',
            with: [
                'inviterName'          => $this->inviterName,
                'tenantName'           => $this->tenantName,
                'inviteeEmail'         => $this->inviteeEmail,
                'roleLabel'            => $this->roleLabel,
                'expiresAt'            => $this->expiresAt,
                'usersUrl'             => $this->usersUrl,
                'inviterReminderNumber' => $this->inviterReminderNumber,
            ],
        );
    }
}
