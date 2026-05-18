<?php

namespace App\Mail;

use App\Models\Tenant;
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
        public Tenant  $tenant,
        public int     $days,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [new Address($this->tenant->admin_email, $this->tenant->name)],
            subject: "Your ReferralBunny.ai access has been extended by {$this->days} day" . ($this->days > 1 ? 's' : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.access-extended',
            with: [
                'tenantId'        => $this->tenant->id,
                'tenantName'      => $this->tenant->name,
                'tenantAdminName' => $this->tenant->admin_name ?? $this->tenant->name,
                'days'            => $this->days,
                'note'            => $this->note,
            ],
        );
    }
}
