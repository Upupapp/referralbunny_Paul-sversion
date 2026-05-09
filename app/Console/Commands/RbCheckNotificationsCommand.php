<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RbCheckNotificationsCommand extends Command
{
    protected $signature = 'rb:notifications:check
                            {--fix : Fix orphaned notifications and migrate wrong notifiable_type values}';

    protected $description = 'Diagnose notification integrity. Use --fix to repair orphaned records and type mismatches.';

    public function handle(): int
    {
        $issues = 0;

        // ── 1. NULL notifiable_id ────────────────────────────────────────
        $nullRows = DB::table('notifications')
            ->whereNull('notifiable_id')
            ->orderBy('created_at')
            ->get(['id', 'type', 'tenant_id', 'created_at']);

        $total   = $nullRows->count();
        $system  = $nullRows->where('type', 'system')->count();
        $orphaned = $total - $system;
        $this->info("NULL notifiable_id: {$total} ({$system} system, {$orphaned} orphaned)");
        $issues += $orphaned;

        if ($this->option('fix') && $orphaned > 0) {
            $deleted = DB::table('notifications')
                ->whereNull('notifiable_id')
                ->where('type', '!=', 'system')
                ->delete();
            $this->info("  ✓ Deleted {$deleted} orphaned records.");
        }

        // ── 2. Wrong notifiable_type (legacy App\Models\* strings) ───────
        $wrongTenant = DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\TenantUser')
            ->count();
        $wrongReseller = DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\Reseller')
            ->count();

        if ($wrongTenant > 0) {
            $this->warn("Wrong notifiable_type 'App\\Models\\TenantUser': {$wrongTenant} rows (should be 'tenant_admin')");
            $issues += $wrongTenant;
            if ($this->option('fix')) {
                DB::table('notifications')
                    ->where('notifiable_type', 'App\\Models\\TenantUser')
                    ->update(['notifiable_type' => 'tenant_admin']);
                $this->info("  ✓ Migrated {$wrongTenant} rows to 'tenant_admin'.");
            }
        }

        if ($wrongReseller > 0) {
            $this->warn("Wrong notifiable_type 'App\\Models\\Reseller': {$wrongReseller} rows (should be 'reseller')");
            $issues += $wrongReseller;
            if ($this->option('fix')) {
                DB::table('notifications')
                    ->where('notifiable_type', 'App\\Models\\Reseller')
                    ->update(['notifiable_type' => 'reseller']);
                $this->info("  ✓ Migrated {$wrongReseller} rows to 'reseller'.");
            }
        }

        // ── Summary ───────────────────────────────────────────────────────
        $this->newLine();
        if ($issues === 0) {
            $this->info('All notification records look healthy.');
        } elseif (!$this->option('fix')) {
            $this->comment("Run with --fix to repair {$issues} issue(s).");
        } else {
            $this->info('All issues repaired.');
        }

        return self::SUCCESS;
    }
}
