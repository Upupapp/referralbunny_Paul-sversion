<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResellerPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $resellerName,
        public string $resellerEmail,
        public string $tenantName,
        public string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [$this->resellerEmail => $this->resellerName],
            subject: "Reset your referrer portal password",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reseller-password-reset',
            with: [
                'resellerName' => $this->resellerName,
                'tenantName'   => $this->tenantName,
                'resetUrl'     => $this->resetUrl,
            ],
        );
    }
}
