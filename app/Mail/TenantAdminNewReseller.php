<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantAdminNewReseller extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $adminName,
        public string $tenantName,
        public string $resellerName,
        public string $resellerEmail,
        public string $joinDate,
        public string $dashboardUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New referrer joined: {$this->resellerName}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tenant-admin-new-reseller');
    }
}
