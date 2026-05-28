<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ArchiveRequestRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $recipientEmail,
        public string  $dealName,
        public string  $stage,
        public ?string $dealUrl,
        public ?string $reviewerNote,
        public string  $resellerName,
        public string  $tenantName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Archive request rejected for \"{$this->dealName}\"",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.deals.archive-request-rejected');
    }
}
