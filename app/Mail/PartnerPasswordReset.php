<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $partnerName,
        public string $partnerEmail,
        public string $tenantName,
        public string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [new Address($this->partnerEmail, $this->partnerName)],
            subject: "Reset your Partner Portal password",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partner-password-reset',
            with: [
                'partnerName' => $this->partnerName,
                'tenantName'  => $this->tenantName,
                'resetUrl'    => $this->resetUrl,
            ],
        );
    }
}
