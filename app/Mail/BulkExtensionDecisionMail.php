<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the referrer after a bulk extension batch is fully or partially reviewed.
 * Clusters all deal outcomes into one summary email — never one email per deal.
 */
class BulkExtensionDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $referrerName,
        public string  $tenantName,
        public string  $batchReference,
        public int     $approvedCount,
        public int     $declinedCount,
        public int     $skippedCount,
        public int     $totalCount,
        public string  $overallStatus,   // 'approved' | 'rejected' | 'partially_approved' | 'partially_declined'
        public ?string $adminNote,
        public string  $requestUrl,
        public array   $dealSummaries = [], // top 10: [['name' => ..., 'status' => ..., 'approved_days' => ...]]
    ) {}

    public function envelope(): Envelope
    {
        $subject = match (true) {
            $this->approvedCount > 0 && $this->declinedCount === 0 => "Your bulk extension request was approved — {$this->approvedCount} deal" . ($this->approvedCount !== 1 ? 's' : ''),
            $this->declinedCount > 0 && $this->approvedCount === 0 => "Your bulk extension request was rejected",
            default                                                  => "Your bulk extension request: {$this->approvedCount} approved, {$this->declinedCount} rejected",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.bulk-extension-decision');
    }
}
