<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExportExpired extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $requesterName,
        public string $exportType,
        public string $tenantName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your export file has expired — ReferralBunny.ai',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.export-expired',
            with: [
                'requesterName' => $this->requesterName,
                'exportType'    => $this->exportType,
                'tenantName'    => $this->tenantName,
            ],
        );
    }
}
