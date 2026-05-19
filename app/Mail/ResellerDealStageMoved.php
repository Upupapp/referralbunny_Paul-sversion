<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResellerDealStageMoved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $resellerName,
        public string  $resellerEmail,
        public string  $tenantName,
        public string  $dealName,
        public string  $fromStage,
        public string  $toStage,
        public float   $dealValue,
        public string  $movedByName,
        public string  $dealUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [new Address($this->resellerEmail, $this->resellerName)],
            subject: "Deal stage updated: {$this->dealName} → " . ucfirst(str_replace('_', ' ', $this->toStage)),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reseller-deal-stage-moved');
    }
}
