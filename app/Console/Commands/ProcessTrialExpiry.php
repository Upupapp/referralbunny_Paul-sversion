<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\BillingService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class ProcessTrialExpiry extends Command
{
    protected $signature   = 'billing:process-trials';
    protected $description = 'Process expiring and expired trials';

    public function handle(BillingService $billing, NotificationService $notifications): void
    {
        // Warn tenants with 3 days left
        $expiringSoon = Subscription::where('status', 'trial')
            ->whereDate('trial_end_date', today()->addDays(3))
            ->get();

        foreach ($expiringSoon as $sub) {
            $notifications->notifyTrialExpiring($sub->tenant_id, 3);
            $this->line("Trial expiry warning sent: {$sub->tenant_id}");
        }

        // Warn tenants with 1 day left
        $expiringTomorrow = Subscription::where('status', 'trial')
            ->whereDate('trial_end_date', today()->addDay())
            ->get();

        foreach ($expiringTomorrow as $sub) {
            $notifications->notifyTrialExpiring($sub->tenant_id, 1);
        }

        // Handle expired trials
        $expired = Subscription::where('status', 'trial')
            ->whereDate('trial_end_date', '<', today())
            ->get();

        foreach ($expired as $sub) {
            $billing->handleTrialExpiry($sub);
            $this->line("Trial expired handled: {$sub->tenant_id}");
        }

        $this->info("Trials processed: {$expiringSoon->count()} warnings, {$expired->count()} expired.");
    }
}
