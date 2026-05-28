<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResellerDealAssigned extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $resellerName,
        public string  $resellerEmail,
        public string  $tenantName,
        public string  $dealName,
        public string  $stage,
        public float   $dealValue,
        public string  $assignedByName,
        public string  $dealUrl,
        public bool    $isReassignment = false,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->isReassignment
            ? "You've been reassigned to a deal: {$this->dealName}"
            : "You've been assigned to a deal: {$this->dealName}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reseller-deal-assigned');
    }
}
