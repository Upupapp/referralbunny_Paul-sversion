<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExportReady extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $requesterName,
        public string $exportType,
        public string $tenantName,
        public string $downloadUrl,
        public string $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your export file is ready — ReferralBunny.ai',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.export-ready',
            with: [
                'requesterName' => $this->requesterName,
                'exportType'    => $this->exportType,
                'tenantName'    => $this->tenantName,
                'downloadUrl'   => $this->downloadUrl,
                'expiresAt'     => $this->expiresAt,
            ],
        );
    }
}
