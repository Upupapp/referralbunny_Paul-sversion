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

class ArchiveRequestRespondedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DealApprovalRequest $archiveRequest,
        public Reseller $reseller,
        public Tenant $tenant,
        public string $adminName,
    ) {}

    public function envelope(): Envelope
    {
        $dealName = $this->archiveRequest->lead?->name
            ?? ($this->archiveRequest->request_payload['deal_name'] ?? 'a deal');
        return new Envelope(
            subject: "Referrer responded to archive clarification — \"{$dealName}\"",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.deals.archive-request-responded');
    }
}
