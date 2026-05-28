<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ArchiveRequestRespondedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $recipientEmail,
        public string  $dealName,
        public string  $dealUrl,
        public ?string $visibleResponse,
        public string  $tenantName,
        public string  $resellerName,
        public string  $adminName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Referrer responded to archive clarification — \"{$this->dealName}\"",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.deals.archive-request-responded');
    }
}
