<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RbCheckNotificationsCommand extends Command
{
    protected $signature = 'rb:notifications:check
                            {--fix : Delete orphaned notifications (NULL notifiable_id, non-system type)}';

    protected $description = 'List notifications with NULL notifiable_id. Use --fix to delete orphaned records.';

    public function handle(): int
    {
        $rows = DB::table('notifications')
            ->whereNull('notifiable_id')
            ->orderBy('created_at')
            ->get(['id', 'type', 'tenant_id', 'created_at']);

        $total     = $rows->count();
        $system    = $rows->where('type', 'system')->count();
        $orphaned  = $total - $system;

        $this->info("Notifications with NULL notifiable_id: {$total} total ({$system} system, {$orphaned} non-system)");

        if ($total === 0) {
            $this->line('No records found — notification recipients look healthy.');
            return self::SUCCESS;
        }

        $this->newLine();
        $headers = ['ID', 'Type', 'Tenant ID', 'Created At'];
        $this->table($headers, $rows->map(fn($r) => [
            substr($r->id, 0, 8) . '…',
            $r->type,
            $r->tenant_id ?? '—',
            $r->created_at,
        ])->toArray());

        // Group by type so we can see which code path is creating them
        $this->newLine();
        $this->line('Breakdown by type:');
        $rows->groupBy('type')->each(function ($group, $type) {
            $this->line("  {$type}: {$group->count()}");
        });

        if ($this->option('fix')) {
            $deleted = DB::table('notifications')
                ->whereNull('notifiable_id')
                ->where('type', '!=', 'system')
                ->delete();

            $this->newLine();
            $this->info("Deleted {$deleted} orphaned non-system notification(s).");
        } else {
            $this->newLine();
            $this->comment('Run with --fix to delete the non-system orphaned records.');
        }

        return self::SUCCESS;
    }
}
