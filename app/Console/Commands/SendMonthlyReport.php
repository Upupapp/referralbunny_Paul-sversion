<?php

namespace App\Console\Commands;

use App\Services\AnalyticsService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendMonthlyReport extends Command
{
    protected $signature   = 'reports:monthly';
    protected $description = 'Send monthly report to super admins';

    public function handle(AnalyticsService $analytics, NotificationService $notifications): void
    {
        $report = $analytics->monthlyReport();

        $notifications->send(
            category:  'analytics',
            type:      'info',
            priority:  'low',
            message:   "Monthly Report ({$report['period']}): {$report['new_tenants']} new tenants · {$report['new_leads']} new leads · {$report['closed_deals']} deals closed.",
            channel:   'email',
            metadata:  $report,
        );

        $this->info("Monthly report sent for {$report['period']}.");
    }
}
