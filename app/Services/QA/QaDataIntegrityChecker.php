<?php

namespace App\Services\QA;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Checks database-level data integrity across all tenant-scoped tables.
 * All queries are read-only. Never modifies data.
 */
class QaDataIntegrityChecker
{
    public function run(?string $tenantId = null): array
    {
        $results = [];

        $results = array_merge($results, $this->checkTableExists());
        $results = array_merge($results, $this->checkOrphanRecords($tenantId));
        $results = array_merge($results, $this->checkMissingTenantScope($tenantId));
        $results = array_merge($results, $this->checkDuplicates($tenantId));
        $results = array_merge($results, $this->checkQueueHealth());
        $results = array_merge($results, $this->checkFailedJobs());

        return $results;
    }

    // ── Table existence ────────────────────────────────────────────────────

    private function checkTableExists(): array
    {
        $results = [];

        $requiredTables = [
            'tenants', 'users', 'tenant_users', 'tenant_memberships',
            'resellers', 'partner_users', 'contacts', 'organizations',
            'leads', 'commission_splits', 'deal_partners', 'deal_partner_splits',
            'deal_assignment_extension_requests',
            'notifications', 'activity_logs', 'messages',
            'import_jobs', 'export_requests',
            'jobs', 'failed_jobs',
        ];

        foreach ($requiredTables as $table) {
            if (Schema::hasTable($table)) {
                $results[] = $this->pass("table.exists.{$table}", "Table '{$table}' exists", 'data_integrity');
            } else {
                $results[] = $this->critical("table.exists.{$table}", "Table '{$table}' is MISSING — run migrations", 'data_integrity');
            }
        }

        return $results;
    }

    // ── Orphan records ─────────────────────────────────────────────────────

    private function checkOrphanRecords(?string $tenantId): array
    {
        $results = [];

        // Commission splits with non-existent lead_id
        if (Schema::hasTable('commission_splits') && Schema::hasTable('leads')) {
            $orphanSplits = DB::table('commission_splits as cs')
                ->leftJoin('leads as l', 'cs.lead_id', '=', 'l.id')
                ->whereNull('l.id')
                ->count();

            if ($orphanSplits === 0) {
                $results[] = $this->pass('integrity.commission_splits.orphan', 'No orphan commission splits', 'data_integrity');
            } else {
                $results[] = $this->fail('integrity.commission_splits.orphan', "{$orphanSplits} commission splits have non-existent lead_id", 'data_integrity', 'high');
            }
        }

        // Partner splits with non-existent deal_id
        if (Schema::hasTable('deal_partner_splits') && Schema::hasTable('leads')) {
            $query = DB::table('deal_partner_splits as dps')
                ->leftJoin('leads as l', DB::raw('l.id::text'), '=', 'dps.deal_id')
                ->whereNull('l.id')
                ->whereNull('dps.deleted_at');

            if ($tenantId) $query->where('dps.tenant_id', $tenantId);

            $orphanSplits = $query->count();

            if ($orphanSplits === 0) {
                $results[] = $this->pass('integrity.partner_splits.orphan', 'No orphan partner splits', 'data_integrity');
            } else {
                $results[] = $this->fail('integrity.partner_splits.orphan', "{$orphanSplits} partner splits reference non-existent deal_id", 'data_integrity', 'high');
            }
        }

        // Deal partners with non-existent partner_user
        if (Schema::hasTable('deal_partners') && Schema::hasTable('partner_users')) {
            $query = DB::table('deal_partners as dp')
                ->leftJoin('partner_users as pu', 'dp.partner_user_id', '=', 'pu.id')
                ->whereNull('pu.id');

            if ($tenantId) $query->where('dp.tenant_id', $tenantId);

            $orphan = $query->count();

            if ($orphan === 0) {
                $results[] = $this->pass('integrity.deal_partners.orphan', 'No orphan deal_partners (all partner_user_ids resolve)', 'data_integrity');
            } else {
                $results[] = $this->fail('integrity.deal_partners.orphan', "{$orphan} deal_partners reference non-existent partner_user", 'data_integrity', 'high');
            }
        }

        // Tenant memberships pointing to non-existent tenant_user
        if (Schema::hasTable('tenant_memberships') && Schema::hasTable('tenant_users')) {
            $orphan = DB::table('tenant_memberships as tm')
                ->leftJoin('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
                ->whereNull('u.id')
                ->count();

            if ($orphan === 0) {
                $results[] = $this->pass('integrity.tenant_memberships.orphan', 'No orphan tenant memberships', 'data_integrity');
            } else {
                $results[] = $this->fail('integrity.tenant_memberships.orphan', "{$orphan} tenant memberships reference non-existent tenant_user", 'data_integrity', 'critical');
            }
        }

        return $results;
    }

    // ── Missing tenant scope ───────────────────────────────────────────────

    private function checkMissingTenantScope(?string $tenantId): array
    {
        $results = [];

        $tables = [
            'leads'               => 'tenant_id',
            'resellers'           => 'tenant_id',
            'deal_partner_splits' => 'tenant_id',
            'deal_assignment_extension_requests' => 'tenant_id',
            'notifications'       => 'tenant_id',
        ];

        foreach ($tables as $table => $col) {
            if (!Schema::hasTable($table)) continue;

            $query = DB::table($table)->whereNull($col);
            $count = $query->count();

            if ($count === 0) {
                $results[] = $this->pass("integrity.{$table}.tenant_scope", "All {$table} records have {$col}", 'data_integrity');
            } else {
                $results[] = $this->critical("integrity.{$table}.tenant_scope", "{$count} records in {$table} have NULL {$col} — potential cross-tenant leak", 'data_integrity');
            }
        }

        return $results;
    }

    // ── Duplicates ─────────────────────────────────────────────────────────

    private function checkDuplicates(?string $tenantId): array
    {
        $results = [];

        // Duplicate resellers by email in same tenant
        if (Schema::hasTable('resellers')) {
            $query = DB::table('resellers')
                ->select('tenant_id', DB::raw('LOWER(email) as email_norm'), DB::raw('COUNT(*) as cnt'))
                ->groupBy('tenant_id', DB::raw('LOWER(email)'))
                ->havingRaw('COUNT(*) > 1');

            if ($tenantId) $query->where('tenant_id', $tenantId);

            $dupes = $query->count();

            if ($dupes === 0) {
                $results[] = $this->pass('integrity.resellers.duplicate_email', 'No duplicate reseller emails within same tenant', 'data_integrity');
            } else {
                $results[] = $this->fail('integrity.resellers.duplicate_email', "{$dupes} tenant+email combinations appear more than once in resellers table", 'data_integrity', 'high');
            }
        }

        // Duplicate partner splits on same deal+email
        if (Schema::hasTable('deal_partner_splits')) {
            $query = DB::table('deal_partner_splits')
                ->select('tenant_id', 'deal_id', 'partner_email', DB::raw('COUNT(*) as cnt'))
                ->whereNull('deleted_at')
                ->groupBy('tenant_id', 'deal_id', 'partner_email')
                ->havingRaw('COUNT(*) > 1');

            if ($tenantId) $query->where('tenant_id', $tenantId);

            $dupes = $query->count();

            if ($dupes === 0) {
                $results[] = $this->pass('integrity.partner_splits.duplicate', 'No duplicate partner splits per deal+email', 'data_integrity');
            } else {
                $results[] = $this->fail('integrity.partner_splits.duplicate', "{$dupes} duplicate partner+deal combinations in deal_partner_splits", 'data_integrity', 'medium');
            }
        }

        return $results;
    }

    // ── Queue health ───────────────────────────────────────────────────────

    private function checkQueueHealth(): array
    {
        $results = [];

        if (!Schema::hasTable('jobs')) {
            $results[] = $this->warning('queue.jobs_table', 'jobs table does not exist — queue may not be configured', 'queues');
            return $results;
        }

        $pendingJobs = DB::table('jobs')->count();
        $results[] = $this->pass('queue.pending_jobs', "Queue has {$pendingJobs} pending jobs", 'queues');

        if ($pendingJobs > 500) {
            $results[] = $this->warning('queue.backlog', "{$pendingJobs} pending jobs — possible queue backlog", 'queues', 'high');
        }

        return $results;
    }

    private function checkFailedJobs(): array
    {
        $results = [];

        if (!Schema::hasTable('failed_jobs')) {
            $results[] = $this->info('queue.failed_jobs_table', 'failed_jobs table does not exist', 'queues');
            return $results;
        }

        $failed = DB::table('failed_jobs')->count();

        if ($failed === 0) {
            $results[] = $this->pass('queue.failed_jobs', 'No failed jobs', 'queues');
        } else {
            $recent = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(3)->get();
            $details = [];
            foreach ($recent as $job) {
                $details[] = $job->queue . ' — ' . substr($job->exception ?? '', 0, 80);
            }
            $results[] = $this->fail('queue.failed_jobs', "{$failed} failed jobs found", 'queues', 'high', $details);
        }

        return $results;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function pass(string $check, string $message, string $module = 'data_integrity', string $severity = 'low'): array
    {
        return ['check' => $check, 'status' => 'pass', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => $severity];
    }

    private function fail(string $check, string $message, string $module = 'data_integrity', string $severity = 'high', array $details = []): array
    {
        return ['check' => $check, 'status' => 'fail', 'message' => $message, 'details' => $details, 'module' => $module, 'severity' => $severity];
    }

    private function warning(string $check, string $message, string $module = 'data_integrity', string $severity = 'medium'): array
    {
        return ['check' => $check, 'status' => 'warning', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => $severity];
    }

    private function critical(string $check, string $message, string $module = 'data_integrity', string $severity = 'critical'): array
    {
        return ['check' => $check, 'status' => 'critical', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => $severity];
    }

    private function info(string $check, string $message, string $module = 'data_integrity'): array
    {
        return ['check' => $check, 'status' => 'info', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => 'low'];
    }
}
