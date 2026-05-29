<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Safely purge LGU IDS tenant deals with dry-run, backup, audit, and critical action.
 *
 * Usage:
 *   php artisan referralbunny:lguids-purge-deals --dry-run
 *   php artisan referralbunny:lguids-purge-deals --confirm-lguids --backup --soft-delete
 *   php artisan referralbunny:lguids-purge-deals --confirm-lguids --backup --hard-delete --i-understand-this-is-destructive
 */
class LguIdsPurgeDealsCommand extends Command
{
    const TENANT_ID       = 'lgu-ids';
    const DEFAULT_VALUE   = 4_000_000;

    protected $signature = 'referralbunny:lguids-purge-deals
        {--dry-run         : Preview what would be deleted without making changes}
        {--confirm-lguids  : Required safety flag confirming this targets LGU IDS only}
        {--backup          : Export deal records to JSON before deletion}
        {--soft-delete     : Deactivate/archive deals instead of removing rows}
        {--hard-delete     : Permanently delete deal rows from database}
        {--i-understand-this-is-destructive : Required confirmation for hard-delete}';

    protected $description = 'Safely purge all LGU IDS tenant deals with dry-run, backup, audit, and critical action.';

    public function handle(): int
    {
        $isDryRun    = $this->option('dry-run');
        $isSoft      = $this->option('soft-delete');
        $isHard      = $this->option('hard-delete');
        $doBackup    = $this->option('backup');
        $confirmed   = $this->option('confirm-lguids');
        $destructive = $this->option('i-understand-this-is-destructive');

        // ── Safety checks ───────────────────────────────────────────────
        if (!$isDryRun) {
            if (!$confirmed) {
                $this->error('You must pass --confirm-lguids to run this command on LGU IDS.');
                return 1;
            }
            if ($isHard && !$destructive) {
                $this->error('Hard delete requires --i-understand-this-is-destructive flag.');
                return 1;
            }
            if (!$isSoft && !$isHard) {
                $this->error('Specify --soft-delete or --hard-delete (soft-delete is recommended).');
                return 1;
            }
        }

        // ── Verify tenant is exactly lgu-ids ────────────────────────────
        $tenant = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        if (!$tenant) {
            $this->error('LGU IDS tenant (id: lgu-ids) not found. Aborting.');
            return 1;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>══════════════════════════════════════════</fg=cyan;options=bold>');
        $this->line('<fg=cyan;options=bold>  LGU IDS Deal Purge' . ($isDryRun ? ' — DRY RUN' : '') . '</fg=cyan;options=bold>');
        $this->line('<fg=cyan;options=bold>══════════════════════════════════════════</fg=cyan;options=bold>');
        $this->newLine();

        // ── Gather counts ───────────────────────────────────────────────
        $deals = DB::table('leads')->where('tenant_id', self::TENANT_ID)->get();
        $total    = $deals->count();
        $active   = $deals->whereIn('status', ['active', 'expiring'])->count();
        $expired  = $deals->where('status', 'expired')->count();
        $declined = $deals->where('status', 'declined')->count();
        $paid     = $deals->where('stage', 'paid')->count();

        $dealIds = $deals->pluck('id');

        $partnerCount    = DB::table('deal_partners')->whereIn('deal_id', $dealIds)->count();
        $commissionCount = DB::table('commission_splits')->whereIn('lead_id', $dealIds)->count();
        $noteCount       = DB::table('lead_notes')->whereIn('lead_id', $dealIds)->count();
        $historyCount    = DB::table('lead_history')->whereIn('lead_id', $dealIds)->count();
        $commentCount    = 0;
        try { $commentCount = DB::table('deal_comments')->whereIn('deal_id', $dealIds)->count(); } catch (\Throwable) {}

        $rows = [
            ['Tenant ID',               self::TENANT_ID],
            ['Tenant Name',             $tenant->name],
            ['Total Deals',             $total],
            ['Active/Expiring',         $active],
            ['Expired',                 $expired],
            ['Declined/Cancelled',      $declined],
            ['Paid/Won',                $paid],
            ['Associated Partners',     $partnerCount],
            ['Commission Splits',       $commissionCount],
            ['Notes',                   $noteCount],
            ['History Events',          $historyCount],
            ['Comments',                $commentCount],
        ];

        $this->table(['Item', 'Count'], $rows);
        $this->newLine();

        if ($isDryRun) {
            $this->warn('DRY RUN — no changes made. Run with --confirm-lguids --backup --soft-delete to proceed.');
            return 0;
        }

        // ── Backup ──────────────────────────────────────────────────────
        $backupPath = null;
        if ($doBackup) {
            $backupPath = 'backups/lguids-deals-' . now()->format('Y-m-d-His') . '.json';
            $payload    = [
                'exported_at' => now()->toIso8601String(),
                'tenant_id'   => self::TENANT_ID,
                'total'       => $total,
                'deals'       => $deals->toArray(),
            ];
            Storage::put($backupPath, json_encode($payload, JSON_PRETTY_PRINT));
            $this->info("  ✓ Backup saved: {$backupPath}");
        }

        // ── Confirm before proceeding ───────────────────────────────────
        if (!$this->confirm("About to " . ($isHard ? 'PERMANENTLY DELETE' : 'archive') . " {$total} LGU IDS deals. Continue?")) {
            $this->warn('Aborted by user.');
            return 0;
        }

        // ── Execute ─────────────────────────────────────────────────────
        DB::transaction(function () use ($dealIds, $isSoft, $isHard, $total, $backupPath) {
            if ($isSoft) {
                DB::table('leads')
                    ->where('tenant_id', self::TENANT_ID)
                    ->update(['status' => 'archived', 'updated_at' => now()]);
                $this->info("  ✓ {$total} deals archived (status=archived).");
            } elseif ($isHard) {
                // Detach relations first
                DB::table('commission_splits')->whereIn('lead_id', $dealIds)->delete();
                DB::table('deal_partners')->whereIn('deal_id', $dealIds)->delete();
                DB::table('lead_notes')->whereIn('lead_id', $dealIds)->delete();
                DB::table('lead_history')->whereIn('lead_id', $dealIds)->delete();
                try { DB::table('deal_comments')->whereIn('deal_id', $dealIds)->delete(); } catch (\Throwable) {}
                DB::table('leads')->where('tenant_id', self::TENANT_ID)->delete();
                $this->info("  ✓ {$total} deals hard-deleted.");
            }

            // Audit log
            try {
                DB::table('activity_logs')->insert([
                    'id'        => (string) Str::uuid(),
                    'tenant_id' => self::TENANT_ID,
                    'user_id'   => 'system',
                    'action'    => 'lguids_deals_purged',
                    'entity'    => 'lead',
                    'entity_id' => 'bulk',
                    'metadata'  => [
                        'count'         => $total,
                        'deletion_mode' => $isHard ? 'hard_delete' : 'soft_delete',
                        'backup_path'   => $backupPath,
                        'actor'         => 'artisan_command',
                        'timestamp'     => now()->toIso8601String(),
                    ],
                    'created_at' => now(),
                ]);
            } catch (\Throwable) {}

            // Clear caches
            try {
                \Illuminate\Support\Facades\Cache::forget('dash_counts:lgu-ids');
                \Illuminate\Support\Facades\Cache::forget('tenant_lgu-ids_leads');
            } catch (\Throwable) {}
        });

        $this->newLine();
        $this->info('Purge complete.');
        Log::info('LGU IDS deals purged', [
            'count' => $total,
            'mode'  => $isHard ? 'hard_delete' : 'soft_delete',
            'backup'=> $backupPath,
        ]);

        return 0;
    }
}
