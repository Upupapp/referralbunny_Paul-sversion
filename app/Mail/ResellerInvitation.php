<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResellerInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $resellerName,
        public string  $resellerEmail,
        public string  $tenantName,
        public string  $setupUrl,
        public ?string $dealName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [new Address($this->resellerEmail, $this->resellerName)],
            subject: "You've been invited as a referrer for {$this->tenantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reseller-invitation',
            with: [
                'resellerName' => $this->resellerName,
                'tenantName'   => $this->tenantName,
                'setupUrl'     => $this->setupUrl,
                'dealName'     => $this->dealName,
            ],
        );
    }
}
