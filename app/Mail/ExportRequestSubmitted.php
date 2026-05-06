<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExportRequestSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $requesterName,
        public string $exportType,
        public string $tenantName,
        public string $exportRequestUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your export request was submitted — ReferralBunny.ai',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.export-request-submitted',
            with: [
                'requesterName'    => $this->requesterName,
                'exportType'       => $this->exportType,
                'tenantName'       => $this->tenantName,
                'exportRequestUrl' => $this->exportRequestUrl,
            ],
        );
    }
}
