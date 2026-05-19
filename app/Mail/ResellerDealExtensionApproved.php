<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResellerDealExtensionApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $resellerName,
        public string  $resellerEmail,
        public string  $tenantName,
        public string  $dealName,
        public int     $approvedDays,
        public int     $newDaysLeft,
        public ?string $adminNote,
        public string  $dealUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [new Address($this->resellerEmail, $this->resellerName)],
            subject: "Extension approved — {$this->approvedDays} days added to: {$this->dealName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reseller-deal-extension-approved');
    }
}
