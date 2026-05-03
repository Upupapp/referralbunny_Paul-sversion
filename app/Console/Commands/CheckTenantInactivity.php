<?php

namespace App\Console\Commands;

use App\Models\TenantMetric;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckTenantInactivity extends Command
{
    protected $signature   = 'metrics:check-inactivity';
    protected $description = 'Check for inactive tenants and send alerts';

    public function handle(NotificationService $notifications): void
    {
        $thresholds = [7, 14, 30];

        foreach ($thresholds as $days) {
            $metrics = TenantMetric::whereNotNull('last_activity_at')
                ->where('last_activity_at', '<', now()->subDays($days))
                ->get();

            foreach ($metrics as $metric) {
                $daysSince = (int) now()->diffInDays($metric->last_activity_at);
                if ($daysSince === $days) {
                    $notifications->notifyTenantInactive($metric->tenant_id, $daysSince);
                    $this->line("Alert: {$metric->tenant_id} inactive {$daysSince}d");
                }
            }
        }

        $this->info('Inactivity check complete.');
    }
}
