<?php

namespace App\Console\Commands;

use App\Events\DealExpired;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireLeads extends Command
{
    protected $signature   = 'leads:expire';
    protected $description = 'Decrement days_left and auto-set expiring/expired status on deals';

    public function handle(): int
    {
        // Collect leads about to expire BEFORE the bulk update so we can dispatch events.
        $aboutToExpire = DB::table('leads')
            ->whereIn('status', ['active', 'expiring'])
            ->where('days_left', '<=', 0)
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'tenant_id', 'reseller_name', 'stage']);

        // Expire: days_left has hit 0 or below
        $expired = DB::table('leads')
            ->whereIn('status', ['active', 'expiring'])
            ->where('days_left', '<=', 0)
            ->whereNull('deleted_at')
            ->update(['status' => 'expired']);

        // Dispatch per-deal events so listeners (admin/reseller notifications) fire correctly.
        foreach ($aboutToExpire as $lead) {
            try {
                DealExpired::dispatch(
                    (string) $lead->id,
                    (string) $lead->name,
                    (string) $lead->tenant_id,
                    $lead->reseller_name ?: null,
                    (string) ($lead->stage ?? ''),
                );
            } catch (\Throwable $e) {
                Log::warning('[ExpireLeads] DealExpired dispatch failed', [
                    'lead_id'   => $lead->id,
                    'tenant_id' => $lead->tenant_id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // Warn: days_left is 1–7 and still active
        $expiring = DB::table('leads')
            ->where('status', 'active')
            ->whereBetween('days_left', [1, 7])
            ->whereNull('deleted_at')
            ->update(['status' => 'expiring']);

        // Decrement days_left for all still-active/expiring deals
        $decremented = DB::table('leads')
            ->whereIn('status', ['active', 'expiring'])
            ->where('days_left', '>', 0)
            ->whereNull('deleted_at')
            ->decrement('days_left');

        $this->info("Expired: {$expired} | Expiring: {$expiring} | Decremented: {$decremented}");

        return self::SUCCESS;
    }
}
