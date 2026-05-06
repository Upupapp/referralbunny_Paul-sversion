<?php

namespace App\Mail;

use App\Models\ContactRoleInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactRoleInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $contactName;
    public string $tenantName;
    public string $role;
    public string $roleLabel;
    public string $setupUrl;
    public ?string $dealName;
    public ?string $customMessage;

    public function __construct(ContactRoleInvitation $invitation, string $contactName, string $tenantName, ?string $dealName = null)
    {
        $this->contactName   = $contactName;
        $this->tenantName    = $tenantName;
        $this->role          = $invitation->invited_role;
        $this->roleLabel     = $invitation->roleLabel();
        $this->dealName      = $dealName;
        $this->customMessage = $invitation->message;

        $this->setupUrl = match ($invitation->invited_role) {
            'referrer' => url('/reseller/setup?token=' . $invitation->token),
            'partner'  => url('/partner/setup?token=' . $invitation->token),
            default    => url('/tenant/accept-role-invite/' . $invitation->token),
        };
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->role) {
            'referrer'       => "You're invited to join {$this->tenantName} as a Referrer",
            'tenant_manager' => "You're invited to help manage {$this->tenantName}",
            'tenant_staff'   => "You're invited to join {$this->tenantName} as Staff",
            'partner'        => "You're invited as a Partner on a referral deal",
            default          => "You've been invited to join {$this->tenantName}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-role-invitation');
    }
}
