<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LguIdsReferrerDealNoteReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $referrerName,
        public array  $deals,       // [{id, name, stage, status, days_left, deal_value, url}]
        public string $tenantName,
        public string $dealsUrl,
        public string $weekLabel,
    ) {}

    public function envelope(): Envelope
    {
        $count = count($this->deals);
        $label = $count === 1
            ? "Your deal needs a note"
            : "{$count} of your deals need notes";

        return new Envelope(
            subject: "📝 {$label} — {$this->tenantName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lgu-ids-referrer-deal-note-reminder');
    }
}
