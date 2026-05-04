<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireLeads extends Command
{
    protected $signature   = 'leads:expire';
    protected $description = 'Decrement days_left and auto-set expiring/expired status on deals';

    public function handle(): int
    {
        // Expire: days_left has hit 0 or below
        $expired = DB::table('leads')
            ->whereIn('status', ['active', 'expiring'])
            ->where('days_left', '<=', 0)
            ->update(['status' => 'expired']);

        // Warn: days_left is 1–7 and still active
        $expiring = DB::table('leads')
            ->where('status', 'active')
            ->whereBetween('days_left', [1, 7])
            ->update(['status' => 'expiring']);

        // Decrement days_left for all still-active/expiring deals
        $decremented = DB::table('leads')
            ->whereIn('status', ['active', 'expiring'])
            ->where('days_left', '>', 0)
            ->decrement('days_left');

        $this->info("Expired: {$expired} | Expiring: {$expiring} | Decremented: {$decremented}");

        return self::SUCCESS;
    }
}
