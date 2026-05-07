<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseExpiryDigestsCommand extends Command
{
    protected $signature   = 'referralbunny:diagnose-expiry-digests {tenant}';
    protected $description = 'Diagnose expiry notification digest status for a tenant';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenant   = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) { $this->error("Tenant '{$tenantId}' not found."); return 1; }

        $today = now()->format('Y-m-d');

        $this->info("─────────────────────────────────────────────────────");
        $this->info(" Expiry Digest Diagnosis — {$tenant->name}");
        $this->info("─────────────────────────────────────────────────────");

        // Expiring deals
        $expiring = DB::table('leads')->where('tenant_id', $tenantId)->where('status', 'expiring')->count();
        $expired  = DB::table('leads')->where('tenant_id', $tenantId)->where('status', 'expired')->count();

        $this->line(" Expiring deals : {$expiring}");
        $this->line(" Expired deals  : {$expired}");

        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" IN-APP NOTIFICATION STATUS (TODAY: {$today})");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $adminSentToday = DB::table('notifications')
            ->where('tenant_id', $tenantId)
            ->whereRaw("metadata_json->>'type' = 'tenant_expiring_daily'")
            ->whereRaw("metadata_json->>'date' = ?", [$today])
            ->count();

        $resellerSentToday = DB::table('notifications')
            ->where('tenant_id', $tenantId)
            ->whereRaw("metadata_json->>'type' = 'reseller_expiring_daily'")
            ->whereRaw("metadata_json->>'date' = ?", [$today])
            ->count();

        $partnerSentToday = DB::table('notifications')
            ->where('tenant_id', $tenantId)
            ->whereRaw("metadata_json->>'type' = 'partner_expiring_daily'")
            ->whereRaw("metadata_json->>'date' = ?", [$today])
            ->count();

        $this->line(" Tenant admin digests sent today   : {$adminSentToday}");
        $this->line(" Referrer digests sent today       : {$resellerSentToday}");
        $this->line(" Partner digests sent today        : {$partnerSentToday}");

        // Active partners on expiring deals
        $activePartnersOnExpiring = DB::table('deal_partner_splits as dps')
            ->join('leads as l', function($j) {
                $j->on('l.id', '=', 'dps.deal_id')
                  ->whereColumn('l.tenant_id', 'dps.tenant_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->where('l.status', 'expiring')
            ->where('dps.status', 'active')
            ->whereNotNull('dps.partner_user_id')
            ->whereNull('dps.deleted_at')
            ->distinct('dps.partner_user_id')
            ->count('dps.partner_user_id');

        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" RECIPIENTS");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" Active partners on expiring deals : {$activePartnersOnExpiring}");

        if ($expiring > 0 && $adminSentToday === 0) {
            $this->warn(" ⚠ Expiring deals exist but no admin digest sent today. Run: php artisan leads:notify-expiring");
        } else {
            $this->line(" ✓ Admin digest status is current for today.");
        }

        $this->newLine();
        $this->line(" Diagnosis complete.");
        return 0;
    }
}
