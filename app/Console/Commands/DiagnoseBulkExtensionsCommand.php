<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseBulkExtensionsCommand extends Command
{
    protected $signature = 'referralbunny:diagnose-bulk-extensions
                            {tenant : Tenant ID to diagnose}
                            {--pending : Show only pending batches and their age}
                            {--conflicts : Show only status-conflict items}
                            {--repair-safe : Auto-fix conflicts (skips pending items on resolved batches; never touches approved/rejected)}';

    protected $description = 'Diagnose bulk deal extension request batches for a tenant';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenant   = DB::table('tenants')->where('id', $tenantId)->first();

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return self::FAILURE;
        }

        $this->info('─────────────────────────────────────────────────────────');
        $this->info(" Bulk Extension Diagnosis — {$tenant->name} ({$tenantId})");
        $this->info('─────────────────────────────────────────────────────────');
        $this->newLine();

        $showAll = !$this->option('pending') && !$this->option('conflicts') && !$this->option('repair-safe');

        if ($showAll) {
            $this->showOverview($tenantId);
        }

        if ($showAll || $this->option('pending')) {
            $this->showPendingBatches($tenantId);
        }

        if ($showAll || $this->option('conflicts')) {
            $this->showConflicts($tenantId);
        }

        if ($this->option('repair-safe')) {
            $this->repairSafe($tenantId);
        }

        return self::SUCCESS;
    }

    private function showOverview(string $tenantId): void
    {
        $this->line('━━━━━  BATCH OVERVIEW  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $batchRow = DB::table('deal_extension_request_batches')
            ->selectRaw("
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'pending')            AS pending,
                COUNT(*) FILTER (WHERE status = 'approved')           AS approved,
                COUNT(*) FILTER (WHERE status = 'declined')           AS declined,
                COUNT(*) FILTER (WHERE status = 'partially_approved') AS partially_approved,
                COUNT(*) FILTER (WHERE status = 'partially_declined') AS partially_declined,
                COUNT(*) FILTER (WHERE status = 'cancelled')          AS cancelled
            ")
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();

        $itemRow = DB::table('deal_assignment_extension_requests')
            ->selectRaw("
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'pending_review') AS pending,
                COUNT(*) FILTER (WHERE status = 'approved')       AS approved,
                COUNT(*) FILTER (WHERE status = 'rejected')       AS rejected,
                COUNT(*) FILTER (WHERE status = 'skipped')        AS skipped,
                COUNT(*) FILTER (WHERE status = 'cancelled')      AS cancelled
            ")
            ->where('tenant_id', $tenantId)
            ->first();

        $this->line(' Batches:');
        $this->line("   Total              : {$batchRow->total}");
        $this->line("   Pending            : {$batchRow->pending}" . ($batchRow->pending > 0 ? ' ← needs review' : ''));
        $this->line("   Approved           : {$batchRow->approved}");
        $this->line("   Partially Approved : {$batchRow->partially_approved}");
        $this->line("   Declined           : {$batchRow->declined}");
        $this->line("   Partially Declined : {$batchRow->partially_declined}");
        $this->line("   Cancelled          : {$batchRow->cancelled}");
        $this->newLine();
        $this->line(' Deal items:');
        $this->line("   Total              : {$itemRow->total}");
        $this->line("   Pending review     : {$itemRow->pending}" . ($itemRow->pending > 0 ? ' ← awaiting decision' : ''));
        $this->line("   Approved           : {$itemRow->approved}");
        $this->line("   Rejected           : {$itemRow->rejected}");
        $this->line("   Skipped            : {$itemRow->skipped}");
        $this->line("   Cancelled          : {$itemRow->cancelled}");
        $this->newLine();
    }

    private function showPendingBatches(string $tenantId): void
    {
        $this->line('━━━━━  PENDING BATCHES  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $batches = DB::table('deal_extension_request_batches')
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get(['id', 'batch_reference', 'total_items', 'requested_extension_days', 'requested_by_reseller_id', 'created_at']);

        if ($batches->isEmpty()) {
            $this->line(' No pending batches. ✓');
            $this->newLine();
            return;
        }

        $rows = [];
        foreach ($batches as $batch) {
            $pendingItems = DB::table('deal_assignment_extension_requests')
                ->where('batch_id', $batch->id)
                ->where('status', 'pending_review')
                ->count();

            $createdAt = \Carbon\Carbon::parse($batch->created_at);
            $ageDays   = $createdAt->diffInDays(now());
            $age       = $ageDays === 0 ? 'today' : ($ageDays === 1 ? '1 day ago' : "{$ageDays} days ago");

            $rows[] = [
                $batch->batch_reference,
                $batch->total_items,
                $pendingItems,
                $batch->requested_extension_days . 'd',
                $age,
                substr($batch->id, 0, 8) . '...',
            ];
        }

        $this->table(
            ['Reference', 'Total Items', 'Pending Items', 'Req. Days', 'Age', 'Batch ID'],
            $rows
        );
        $this->newLine();
    }

    private function showConflicts(string $tenantId): void
    {
        $this->line('━━━━━  STATUS CONFLICTS  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Items still pending_review on batches that have been resolved
        $conflicted = DB::table('deal_assignment_extension_requests as i')
            ->join('deal_extension_request_batches as b', 'i.batch_id', '=', 'b.id')
            ->where('i.tenant_id', $tenantId)
            ->where('i.status', 'pending_review')
            ->whereIn('b.status', ['approved', 'declined', 'partially_approved', 'partially_declined', 'cancelled'])
            ->select('i.id', 'i.deal_id', 'b.batch_reference', 'b.status as batch_status', 'i.created_at')
            ->get();

        if ($conflicted->isEmpty()) {
            $this->line(' No status conflicts found. ✓');
        } else {
            $this->warn(" Found {$conflicted->count()} item(s) with pending_review status on resolved batches:");
            $rows = $conflicted->map(fn($i) => [
                substr($i->id, 0, 8) . '...',
                $i->batch_reference,
                $i->batch_status,
                substr($i->deal_id, 0, 8) . '...',
            ])->toArray();
            $this->table(['Item ID', 'Batch Reference', 'Batch Status', 'Deal ID'], $rows);
            $this->warn(' Run with --repair-safe to fix these.');
        }

        // Batches where total_items doesn't match actual item count
        $countMismatches = DB::table('deal_extension_request_batches as b')
            ->leftJoinSub(
                DB::table('deal_assignment_extension_requests')
                    ->selectRaw('batch_id, COUNT(*) as actual_count')
                    ->groupBy('batch_id'),
                'counts',
                'b.id',
                '=',
                'counts.batch_id'
            )
            ->where('b.tenant_id', $tenantId)
            ->whereNull('b.deleted_at')
            ->whereRaw('b.total_items != COALESCE(counts.actual_count, 0)')
            ->select('b.id', 'b.batch_reference', 'b.status', 'b.total_items', DB::raw('COALESCE(counts.actual_count, 0) AS actual'))
            ->get();

        if ($countMismatches->isNotEmpty()) {
            $this->newLine();
            $this->warn(" Found {$countMismatches->count()} batch(es) where total_items does not match actual item count:");
            $rows = $countMismatches->map(fn($b) => [
                $b->batch_reference,
                $b->status,
                $b->total_items,
                $b->actual,
                substr($b->id, 0, 8) . '...',
            ])->toArray();
            $this->table(['Reference', 'Status', 'total_items', 'Actual Count', 'Batch ID'], $rows);
        }

        $this->newLine();
    }

    private function repairSafe(string $tenantId): void
    {
        $this->line('━━━━━  SAFE REPAIR  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $conflictedIds = DB::table('deal_assignment_extension_requests as i')
            ->join('deal_extension_request_batches as b', 'i.batch_id', '=', 'b.id')
            ->where('i.tenant_id', $tenantId)
            ->where('i.status', 'pending_review')
            ->whereIn('b.status', ['approved', 'declined', 'partially_approved', 'partially_declined', 'cancelled'])
            ->pluck('i.id');

        if ($conflictedIds->isEmpty()) {
            $this->line(' Nothing to repair. ✓');
            $this->newLine();
            return;
        }

        $count = $conflictedIds->count();
        if (!$this->confirm(" Mark {$count} conflicted item(s) as 'skipped'? (safe — never touches approved/rejected items)")) {
            $this->line(' Repair skipped.');
            $this->newLine();
            return;
        }

        DB::table('deal_assignment_extension_requests')
            ->whereIn('id', $conflictedIds)
            ->update(['status' => 'skipped', 'updated_at' => now()]);

        $this->info(" Repaired {$count} item(s). ✓");
        $this->newLine();
    }
}
