<?php

namespace App\Mail;

use App\Models\TenantInvitation;
use App\Models\TenantUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInvitationAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $inviterName;
    public string $inviterEmail;
    public string $tenantName;
    public string $acceptedByName;
    public string $acceptedByEmail;
    public string $roleLabel;
    public string $usersUrl;

    public function __construct(
        TenantInvitation $invitation,
        TenantUser       $inviter,
        TenantUser       $acceptedBy,
    ) {
        $this->inviterName     = trim("{$inviter->first_name} {$inviter->last_name}");
        $this->inviterEmail    = $inviter->email;
        $this->tenantName      = $invitation->tenant?->name ?? 'the workspace';
        $this->acceptedByName  = trim("{$acceptedBy->first_name} {$acceptedBy->last_name}");
        $this->acceptedByEmail = $acceptedBy->email;
        $this->roleLabel       = ucfirst($invitation->role);
        $this->usersUrl        = url("/tenant/{$invitation->tenant_id}/users");
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->acceptedByName} accepted your invitation to {$this->tenantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-invitation-accepted',
            with: [
                'inviterName'     => $this->inviterName,
                'tenantName'      => $this->tenantName,
                'acceptedByName'  => $this->acceptedByName,
                'acceptedByEmail' => $this->acceptedByEmail,
                'roleLabel'       => $this->roleLabel,
                'usersUrl'        => $this->usersUrl,
            ],
        );
    }
}
