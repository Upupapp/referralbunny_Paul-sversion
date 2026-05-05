<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommissionStatusUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $resellerName,
        public string $resellerEmail,
        public string $tenantName,
        public string $dealName,
        public string $status,   // pending | locked | paid
        public float  $dealValue,
        public string $dashboardUrl,
    ) {}

    public function envelope(): Envelope
    {
        $labels = ['pending' => 'Commission pending', 'locked' => 'Commission locked', 'paid' => 'Commission paid'];
        return new Envelope(
            to:      [new Address($this->resellerEmail, $this->resellerName)],
            subject: ($labels[$this->status] ?? 'Commission update') . " for {$this->dealName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.commission-status-update');
    }
}
