<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to tenant Admins and Managers when an import batch hard-fails.
 * NOT sent for 'completed_with_warnings' — that's in-app only to avoid noise.
 */
class ImportFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $tenantName,
        public readonly string $fileName,
        public readonly string $typeLabel,   // e.g. "Deal import", "LGU IDS deal import"
        public readonly int    $failedRows,
        public readonly int    $totalRows,
        public readonly string $reportUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Import failed: {$this->fileName} — action required",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.import-failed');
    }
}
