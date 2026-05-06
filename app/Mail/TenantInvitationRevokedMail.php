<?php

namespace App\Mail;

use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInvitationRevokedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $tenantName;
    public string $roleLabel;

    public function __construct(public TenantInvitation $invitation)
    {
        $this->tenantName = $invitation->tenant?->name ?? 'the workspace';
        $this->roleLabel  = ucfirst($invitation->role);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [new Address($this->invitation->email)],
            subject: "Your invitation to {$this->tenantName} has been cancelled",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-invitation-revoked',
            with: [
                'tenantName' => $this->tenantName,
                'roleLabel'  => $this->roleLabel,
            ],
        );
    }
}
