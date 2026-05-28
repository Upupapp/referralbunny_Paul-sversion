<?php

namespace App\Mail;

use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInvitationExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientEmail;
    public string $tenantName;
    public string $inviterName;
    public string $roleLabel;

    public function __construct(TenantInvitation $invitation)
    {
        $this->recipientEmail = $invitation->email;
        $this->tenantName     = $invitation->tenant?->name ?? 'the workspace';
        $this->roleLabel      = ucfirst($invitation->role);

        $inviter           = $invitation->invitedBy;
        $this->inviterName = $inviter
            ? trim("{$inviter->first_name} {$inviter->last_name}")
            : $this->tenantName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your invitation to {$this->tenantName} has expired",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-invitation-expired',
            with: [
                'tenantName'  => $this->tenantName,
                'inviterName' => $this->inviterName,
                'roleLabel'   => $this->roleLabel,
            ],
        );
    }
}
