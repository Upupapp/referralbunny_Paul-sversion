<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantAdminDailyBriefing extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $adminName,
        public string $tenantName,
        public int    $expiringDeals,
        public int    $newDeals,
        public int    $newResellers,
        public int    $activeDeals,
        public array  $expiringList,    // [{name, days_left, stage, reseller}]
        public array  $newDealsList,    // [{name, stage, reseller}]
        public string $dashboardUrl,
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->expiringDeals > 0 ? "⚠️ " : "";
        return new Envelope(
            subject: "{$prefix}Your daily {$this->tenantName} referral briefing",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tenant-admin-daily-briefing');
    }
}
