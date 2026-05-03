<?php

namespace App\Console\Commands;

use App\Services\AnalyticsService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendWeeklySummary extends Command
{
    protected $signature   = 'reports:weekly';
    protected $description = 'Send weekly summary to super admins';

    public function handle(AnalyticsService $analytics, NotificationService $notifications): void
    {
        $digest = $analytics->weeklyDigest();

        $message = "Weekly Summary: {$digest['summary']['total_tenants']} tenants · " .
                   "{$digest['new_tenants']} new · " .
                   "{$digest['at_risk_tenants']} at risk · " .
                   "{$digest['critical_alerts']} critical alerts";

        $notifications->send(
            category:  'analytics',
            type:      'info',
            priority:  'low',
            message:   $message,
            channel:   'email',
            metadata:  $digest,
        );

        $this->info('Weekly summary sent.');
    }
}
