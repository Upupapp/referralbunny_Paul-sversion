<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResellerDailySummary extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $resellerName,
        public string $resellerEmail,
        public string $tenantName,
        public int    $activeDeals,
        public int    $expiringDeals,
        public int    $totalDeals,
        public float  $pendingCommission,
        public array  $expiringList,    // [{name, days_left, stage}]
        public string $dashboardUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [new Address($this->resellerEmail, $this->resellerName)],
            subject: "Your daily referral summary — " . now()->setTimezone('Asia/Manila')->format('M j'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reseller-daily-summary');
    }
}
