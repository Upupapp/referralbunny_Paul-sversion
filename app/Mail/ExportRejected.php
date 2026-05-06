<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExportRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $requesterName,
        public string  $exportType,
        public ?string $rejectionReason,
        public string  $tenantName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your export request was not approved — ReferralBunny.ai',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.export-rejected',
            with: [
                'requesterName'   => $this->requesterName,
                'exportType'      => $this->exportType,
                'rejectionReason' => $this->rejectionReason,
                'tenantName'      => $this->tenantName,
            ],
        );
    }
}
