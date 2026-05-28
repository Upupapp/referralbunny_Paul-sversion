<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $recipientEmail,
        public string  $partnerFirstName,
        public string  $tenantName,
        public string  $inviterName,
        public string  $setupUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [new Address($this->recipientEmail)],
            subject: "You're invited as a Partner — {$this->tenantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.partner-invite',
            with: [
                'partnerFirstName' => $this->partnerFirstName,
                'tenantName'       => $this->tenantName,
                'inviterName'      => $this->inviterName,
                'setupUrl'         => $this->setupUrl,
            ],
        );
    }
}
