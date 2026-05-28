<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the person who invited a Referrer or Partner when that person activates
 * their account.  Does NOT fire for tenant_user invitations — those use
 * TenantInvitationAcceptedMail (sent from TenantInvitationController).
 */
class InviterActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $inviterName,
        public readonly string $inviterEmail,
        public readonly string $acceptedUserName,
        public readonly string $acceptedUserEmail,
        public readonly string $roleLabel,
        public readonly string $tenantName,
        public readonly string $actionUrl,
        public readonly string $actionLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->acceptedUserName} has activated their {$this->roleLabel} account on {$this->tenantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inviter-activation',
            with: [
                'inviterName'      => $this->inviterName,
                'acceptedUserName' => $this->acceptedUserName,
                'acceptedUserEmail'=> $this->acceptedUserEmail,
                'roleLabel'        => $this->roleLabel,
                'tenantName'       => $this->tenantName,
                'actionUrl'        => $this->actionUrl,
                'actionLabel'      => $this->actionLabel,
            ],
        );
    }
}
