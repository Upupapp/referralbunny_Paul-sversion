<?php

namespace App\Mail;

use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientEmail;
    public string $acceptUrl;
    public string $tenantName;
    public string $inviterName;
    public string $roleLabel;
    public string $expiresAt;

    public function __construct(TenantInvitation $invitation)
    {
        $this->recipientEmail = $invitation->email;
        $this->acceptUrl      = url("/tenant/accept-invite/{$invitation->token}");
        $this->tenantName     = $invitation->tenant?->name ?? 'the workspace';
        $this->roleLabel      = ucfirst($invitation->role);
        $this->expiresAt      = $invitation->expires_at->format('F j, Y');

        $inviter           = $invitation->invitedBy;
        $this->inviterName = $inviter
            ? trim("{$inviter->first_name} {$inviter->last_name}")
            : $this->tenantName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [new Address($this->recipientEmail)],
            subject: "You've been invited to join {$this->tenantName} on ReferralBunny.ai",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-invitation',
            with: [
                'acceptUrl'   => $this->acceptUrl,
                'tenantName'  => $this->tenantName,
                'inviterName' => $this->inviterName,
                'roleLabel'   => $this->roleLabel,
                'expiresAt'   => $this->expiresAt,
            ],
        );
    }
}
