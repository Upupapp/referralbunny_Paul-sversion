<?php

namespace App\Mail;

use App\Models\DealApprovalRequest;
use App\Models\Reseller;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ArchiveRequestSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DealApprovalRequest $archiveRequest,
        public Tenant $tenant,
        public string $adminEmail,
        public string $adminName,
    ) {}

    public function envelope(): Envelope
    {
        $dealName = $this->archiveRequest->lead?->name ?? 'a deal';
        return new Envelope(
            subject: "Archive request submitted for \"{$dealName}\"",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.deals.archive-request-submitted');
    }
}
