<?php

namespace App\Console\Commands;

use App\Models\Partner;
use App\Models\Reseller;
use App\Services\ContactSyncService;
use Illuminate\Console\Command;

class SyncUsersAsContactsCommand extends Command
{
    protected $signature = 'contacts:sync-users
                            {--tenant= : Limit sync to a single tenant ID}
                            {--dry-run : Report what would be synced without writing}';

    protected $description = 'Sync all referrers and partners that have an email address into tenant contacts';

    public function handle(ContactSyncService $sync): int
    {
        $tenantFilter = $this->option('tenant');
        $dry          = $this->option('dry-run');

        $this->info($dry ? '[DRY RUN] No records will be written.' : 'Syncing referrers and partners as contacts…');

        // ── Referrers ─────────────────────────────────────────────
        $resellerQuery = Reseller::whereNotNull('email')
            ->where('email', '!=', '')
            ->where('is_anonymous', false);

        if ($tenantFilter) {
            $resellerQuery->where('tenant_id', $tenantFilter);
        }

        $referrers = $resellerQuery->orderBy('tenant_id')->get();
        $rSynced   = 0;
        $rSkipped  = 0;

        foreach ($referrers as $reseller) {
            try {
                if (!$dry) {
                    $sync->syncReseller($reseller);
                }
                $rSynced++;
            } catch (\Throwable $e) {
                $this->warn("  Referrer {$reseller->id} ({$reseller->email}): {$e->getMessage()}");
                $rSkipped++;
            }
        }

        $this->line("  Referrers: {$rSynced} synced" . ($rSkipped ? ", {$rSkipped} errors" : ''));

        // ── Partners ──────────────────────────────────────────────
        $partnerQuery = Partner::whereNotNull('email')->where('email', '!=', '');

        if ($tenantFilter) {
            $partnerQuery->where('tenant_id', $tenantFilter);
        }

        $partners = $partnerQuery->orderBy('tenant_id')->get();
        $pSynced  = 0;
        $pSkipped = 0;

        foreach ($partners as $partner) {
            try {
                if (!$dry) {
                    $sync->syncPartner($partner);
                }
                $pSynced++;
            } catch (\Throwable $e) {
                $this->warn("  Partner {$partner->id} ({$partner->email}): {$e->getMessage()}");
                $pSkipped++;
            }
        }

        $this->line("  Partners:  {$pSynced} synced" . ($pSkipped ? ", {$pSkipped} errors" : ''));
        $this->info('Done.');

        return self::SUCCESS;
    }
}
