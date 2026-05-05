<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SuperAdminDailySummary extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int    $activeTenants,
        public int    $newDealsToday,
        public int    $expiringDeals,
        public int    $newResellers,
        public int    $totalDeals,
        public string $dashboardUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "ReferralBunny.ai Daily Platform Summary — " . now()->setTimezone('Asia/Manila')->format('M j, Y'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.super-admin-daily-summary');
    }
}
