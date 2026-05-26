<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to tenant admins/managers when a bulk extension request is submitted
 * and needs review.
 */
class BulkExtensionAdminNotifyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $adminName,
        public string  $tenantName,
        public string  $referrerName,
        public int     $dealCount,
        public int     $requestedDays,
        public string  $reasonPreview,
        public string  $batchReference,
        public string  $reviewUrl,
        public ?string $earliestExpiry = null,
        public int     $expiredCount   = 0,
        public int     $expiringCount  = 0,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Bulk extension request needs review: {$this->dealCount} deal" . ($this->dealCount !== 1 ? 's' : '') . " — {$this->tenantName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.bulk-extension-admin-notify');
    }
}
