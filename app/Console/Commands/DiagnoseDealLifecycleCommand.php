<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseDealLifecycleCommand extends Command
{
    protected $signature = 'referralbunny:diagnose-deal-lifecycle
                            {tenant : Tenant ID to diagnose}
                            {--expired : Show expired deals details}
                            {--archive-requests : Show archive request queue}
                            {--deleted-archived : Show deleted/archived deals}
                            {--orphans : Show orphaned archive requests (lead deleted but request still pending)}
                            {--fix-orphans : Auto-reject orphaned pending archive requests}
                            {--force : Skip confirmation for --fix-orphans}';

    protected $description = 'Diagnose deal lifecycle health (expired, archive requests, deleted/archived) for a tenant';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenant   = DB::table('tenants')->where('id', $tenantId)->first();

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return self::FAILURE;
        }

        $this->info('──────────────────────────────────────────────────────────');
        $this->info(" Deal Lifecycle Diagnosis — {$tenant->name} ({$tenantId})");
        $this->info('──────────────────────────────────────────────────────────');
        $this->newLine();

        $showAll = !$this->option('expired')
            && !$this->option('archive-requests')
            && !$this->option('deleted-archived')
            && !$this->option('orphans')
            && !$this->option('fix-orphans');

        if ($showAll || $this->option('expired')) {
            $this->showExpired($tenantId);
        }

        if ($showAll || $this->option('archive-requests')) {
            $this->showArchiveRequests($tenantId);
        }

        if ($showAll || $this->option('deleted-archived')) {
            $this->showDeletedArchived($tenantId);
        }

        if ($showAll || $this->option('orphans') || $this->option('fix-orphans')) {
            $orphanCount = $this->showOrphans($tenantId);

            if ($this->option('fix-orphans') && $orphanCount > 0) {
                $this->fixOrphans($tenantId, $orphanCount);
            }
        }

        $this->newLine();
        $this->info('Diagnosis complete.');
        return self::SUCCESS;
    }

    private function showExpired(string $tenantId): void
    {
        $total     = DB::table('leads')->where('tenant_id', $tenantId)->where('status', 'expired')->whereNull('deleted_at')->count();
        $thisWeek  = DB::table('leads')->where('tenant_id', $tenantId)->where('status', 'expired')->whereNull('deleted_at')
            ->where('updated_at', '>=', now()->startOfWeek())->count();
        $totalVal  = (float) DB::table('leads')->where('tenant_id', $tenantId)->where('status', 'expired')->whereNull('deleted_at')->sum('deal_value');

        $this->line('<fg=red>● EXPIRED DEALS</>');
        $this->table(['Metric', 'Count'], [
            ['Total expired',    $total],
            ['Expired this week', $thisWeek],
            ['Total deal value',  '₱' . number_format($totalVal, 0)],
        ]);

        if ($total > 0) {
            $rows = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('status', 'expired')
                ->whereNull('deleted_at')
                ->select('id', 'name', 'stage', 'reseller_name', 'deal_value', 'updated_at')
                ->orderBy('updated_at', 'desc')
                ->limit(10)
                ->get();

            $this->table(
                ['ID', 'Name', 'Stage', 'Referrer', 'Value', 'Expired'],
                $rows->map(fn($r) => [
                    substr($r->id, 0, 8) . '…',
                    \Illuminate\Support\Str::limit($r->name, 30),
                    str_replace('_', ' ', $r->stage),
                    $r->reseller_name ?: '—',
                    '₱' . number_format((float)$r->deal_value, 0),
                    $r->updated_at,
                ])->toArray()
            );
            if ($total > 10) $this->line("  … and " . ($total - 10) . " more.");
        }
        $this->newLine();
    }

    private function showArchiveRequests(string $tenantId): void
    {
        $pending        = DB::table('deal_approval_requests')->where('tenant_id', $tenantId)->where('type', 'deal_archive')->where('status', 'pending')->count();
        $approved       = DB::table('deal_approval_requests')->where('tenant_id', $tenantId)->where('type', 'deal_archive')->where('status', 'approved')->count();
        $rejected       = DB::table('deal_approval_requests')->where('tenant_id', $tenantId)->where('type', 'deal_archive')->where('status', 'rejected')->count();
        $clarification  = DB::table('deal_approval_requests')->where('tenant_id', $tenantId)->where('type', 'deal_archive')->where('status', 'clarification_requested')->count();

        $this->line('<fg=yellow>● ARCHIVE REQUESTS</>');
        $this->table(['Status', 'Count'], [
            ['Pending',                $pending],
            ['Clarification requested', $clarification],
            ['Approved',               $approved],
            ['Rejected',               $rejected],
        ]);

        if ($pending > 0) {
            $rows = DB::table('deal_approval_requests as dar')
                ->join('leads as l', 'l.id', '=', 'dar.deal_id')
                ->where('dar.tenant_id', $tenantId)
                ->where('dar.type', 'deal_archive')
                ->where('dar.status', 'pending')
                ->select('dar.id', 'l.name as deal_name', 'l.stage', 'dar.created_at', 'dar.reason')
                ->orderBy('dar.created_at', 'asc')
                ->limit(10)
                ->get();

            $this->table(
                ['Request ID', 'Deal', 'Stage', 'Submitted', 'Reason'],
                $rows->map(fn($r) => [
                    substr($r->id, 0, 8) . '…',
                    \Illuminate\Support\Str::limit($r->deal_name, 30),
                    str_replace('_', ' ', $r->stage),
                    $r->created_at,
                    \Illuminate\Support\Str::limit($r->reason ?? '—', 40),
                ])->toArray()
            );
        }

        // Stale check: pending requests older than 14 days
        $stale = DB::table('deal_approval_requests')
            ->where('tenant_id', $tenantId)
            ->where('type', 'deal_archive')
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subDays(14))
            ->count();

        if ($stale > 0) {
            $this->warn("  ⚠ {$stale} pending archive request(s) are older than 14 days — consider reviewing.");
        }
        $this->newLine();
    }

    private function showDeletedArchived(string $tenantId): void
    {
        $archived     = DB::table('leads')->where('tenant_id', $tenantId)->where('status', 'archived')->whereNull('deleted_at')->count();
        $deleted      = DB::table('leads')->where('tenant_id', $tenantId)->whereNotNull('deleted_at')->count();
        $archiveVal   = (float) DB::table('leads')->where('tenant_id', $tenantId)->where('status', 'archived')->whereNull('deleted_at')->sum('deal_value');
        $purgesSoon   = DB::table('leads')->where('tenant_id', $tenantId)->whereNotNull('deleted_at')
            ->where('deleted_at', '<=', now()->subDays(8))
            ->count();

        $this->line('<fg=gray>● DELETED / ARCHIVED DEALS</>');
        $this->table(['Metric', 'Count'], [
            ['Archived (status)',    $archived],
            ['Archived value',       '₱' . number_format($archiveVal, 0)],
            ['Soft-deleted',         $deleted],
            ['Purging within 2 days', $purgesSoon],
        ]);

        if ($purgesSoon > 0) {
            $this->warn("  ⚠ {$purgesSoon} deal(s) will be permanently purged within 2 days.");
        }
        $this->newLine();
    }

    private function showOrphans(string $tenantId): int
    {
        // Orphaned = archive request is pending but lead is soft-deleted
        $orphanIds = DB::table('deal_approval_requests as dar')
            ->leftJoin('leads as l', function ($join) {
                $join->on('l.id', '=', 'dar.deal_id')->whereNull('l.deleted_at');
            })
            ->where('dar.tenant_id', $tenantId)
            ->where('dar.type', 'deal_archive')
            ->where('dar.status', 'pending')
            ->whereNull('l.id')
            ->pluck('dar.id');

        $count = $orphanIds->count();

        $this->line('<fg=magenta>● ORPHANED ARCHIVE REQUESTS</>');

        if ($count === 0) {
            $this->info('  ✓ No orphaned archive requests found.');
        } else {
            $this->warn("  ✗ {$count} pending archive request(s) reference soft-deleted or missing leads.");
            $this->table(['Request ID', 'Deal ID'], $orphanIds->map(function ($id) {
                $req = DB::table('deal_approval_requests')->where('id', $id)->first();
                return [substr($id, 0, 8) . '…', substr($req->deal_id ?? '', 0, 8) . '…'];
            })->toArray());
            $this->line('  → Run with --fix-orphans to auto-reject these.');
        }
        $this->newLine();
        return $count;
    }

    private function fixOrphans(string $tenantId, int $count): void
    {
        if (!$this->option('force')) {
            if (!$this->confirm("Auto-reject {$count} orphaned archive request(s)?")) {
                return;
            }
        }

        $affected = DB::table('deal_approval_requests as dar')
            ->leftJoin('leads as l', function ($join) {
                $join->on('l.id', '=', 'dar.deal_id')->whereNull('l.deleted_at');
            })
            ->where('dar.tenant_id', $tenantId)
            ->where('dar.type', 'deal_archive')
            ->where('dar.status', 'pending')
            ->whereNull('l.id')
            ->pluck('dar.id');

        DB::table('deal_approval_requests')
            ->whereIn('id', $affected)
            ->update([
                'status'        => 'rejected',
                'reviewer_note' => 'Auto-rejected: associated deal was deleted.',
                'rejected_at'   => now(),
                'updated_at'    => now(),
            ]);

        $this->info("  ✓ {$affected->count()} orphaned archive request(s) auto-rejected.");
    }
}
