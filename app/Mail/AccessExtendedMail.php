<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccessExtendedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $tenantId,
        public string  $tenantName,
        public string  $tenantAdminName,
        public string  $adminEmail,
        public int     $days,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your ReferralBunny.ai access has been extended by {$this->days} day" . ($this->days > 1 ? 's' : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.access-extended',
            with: [
                'tenantId'        => $this->tenantId,
                'tenantName'      => $this->tenantName,
                'tenantAdminName' => $this->tenantAdminName,
                'days'            => $this->days,
                'note'            => $this->note,
            ],
        );
    }
}
