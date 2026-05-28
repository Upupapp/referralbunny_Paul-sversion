<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResellerRemovedFromDeal extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $resellerName,
        public string  $resellerEmail,
        public string  $tenantName,
        public string  $dealName,
        public string  $reassignedByName,
        public string  $tenantId,
        public ?string $newResellerName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been removed from a deal: {$this->dealName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reseller-deal-removed');
    }
}
