<?php

namespace App\Mail;

use App\Models\DealApprovalRequest;
use App\Models\Reseller;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ArchiveRequestRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DealApprovalRequest $archiveRequest,
        public Reseller $reseller,
        public Tenant $tenant,
    ) {}

    public function envelope(): Envelope
    {
        $dealName = $this->archiveRequest->lead?->name ?? 'your deal';
        return new Envelope(
            to:      [new Address($this->reseller->email, $this->reseller->name)],
            subject: "Archive request rejected for \"{$dealName}\"",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.deals.archive-request-rejected');
    }
}
