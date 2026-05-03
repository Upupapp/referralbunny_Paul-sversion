<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\HealthScoreService;
use Illuminate\Console\Command;

class CalculateTenantMetrics extends Command
{
    protected $signature   = 'metrics:calculate';
    protected $description = 'Recalculate health scores and metrics for all tenants';

    public function handle(HealthScoreService $health): void
    {
        $tenants = Tenant::with(['config', 'resellers', 'subIndustries'])->get();
        $bar     = $this->output->createProgressBar($tenants->count());
        $bar->start();

        foreach ($tenants as $tenant) {
            $health->updateMetrics($tenant);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Health metrics updated for {$tenants->count()} tenants.");
    }
}
