<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LguIdsDealNoteTaskMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $referrerName,
        public string $dealName,
        public string $stageLabel,
        public string $dealUrl,
        public string $tenantName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📝 Action needed: Add a note to "' . $this->dealName . '"',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lgu-ids-deal-note-task');
    }
}
