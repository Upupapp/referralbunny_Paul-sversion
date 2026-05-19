<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResellerDealDeclined extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $resellerName,
        public string  $resellerEmail,
        public string  $tenantName,
        public string  $dealName,
        public string  $stage,
        public float   $dealValue,
        public string  $declinedByName,
        public string  $dashboardUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [new Address($this->resellerEmail, $this->resellerName)],
            subject: "Deal update — {$this->dealName} has been declined",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reseller-deal-declined');
    }
}
