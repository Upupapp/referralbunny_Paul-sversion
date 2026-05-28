<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResellerDealCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $resellerName,
        public string $resellerEmail,
        public string $tenantName,
        public string $dealName,
        public string $stage,
        public int    $daysLeft,
        public float  $dealValue,
        public string $dashboardUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Deal created: {$this->dealName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reseller-deal-created');
    }
}
