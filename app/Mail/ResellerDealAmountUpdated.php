<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResellerDealAmountUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $resellerName,
        public string  $resellerEmail,
        public string  $tenantName,
        public string  $dealName,
        public float   $oldAmount,
        public float   $newAmount,
        public string  $updatedByName,
        public string  $dealUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [new Address($this->resellerEmail, $this->resellerName)],
            subject: "Deal amount updated: {$this->dealName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reseller-deal-amount-updated');
    }
}
