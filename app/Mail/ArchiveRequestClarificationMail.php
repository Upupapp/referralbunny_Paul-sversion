<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ArchiveRequestClarificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $recipientEmail,
        public string  $dealName,
        public ?string $dealUrl,
        public ?string $clarificationMessage,
        public ?string $clarificationDueAt,
        public string  $resellerName,
        public string  $tenantName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [new Address($this->recipientEmail)],
            subject: "Clarification needed for your archive request on \"{$this->dealName}\"",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.deals.archive-request-clarification');
    }
}
