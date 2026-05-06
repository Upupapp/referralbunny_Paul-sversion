<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExportApprovalNeeded extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $adminName,
        public string $requesterName,
        public string $requesterRole,
        public string $exportType,
        public string $reason,
        public string $tenantName,
        public string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Export request needs your approval — {$this->tenantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.export-approval-needed',
            with: [
                'adminName'     => $this->adminName,
                'requesterName' => $this->requesterName,
                'requesterRole' => $this->requesterRole,
                'exportType'    => $this->exportType,
                'reason'        => $this->reason,
                'tenantName'    => $this->tenantName,
                'reviewUrl'     => $this->reviewUrl,
            ],
        );
    }
}
