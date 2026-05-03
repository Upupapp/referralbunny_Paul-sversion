<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\FeatureAccessService;
use Illuminate\Console\Command;

class ResetMonthlyUsage extends Command
{
    protected $signature   = 'usage:reset-monthly';
    protected $description = 'Reset monthly usage counters for all tenants';

    public function handle(FeatureAccessService $service): void
    {
        $tenants = Tenant::all();
        foreach ($tenants as $tenant) {
            $service->resetMonthlyUsage($tenant->id);
        }
        $this->info("Reset monthly usage for {$tenants->count()} tenants.");
    }
}
