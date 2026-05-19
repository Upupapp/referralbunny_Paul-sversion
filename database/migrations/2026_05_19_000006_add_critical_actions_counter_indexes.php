<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * OPTIMIZE: Critical Actions Counter indexes.
 *
 * These composite partial indexes target the four most expensive source queries
 * executed by CriticalActionService::forTenant() on every badge cache-miss:
 *
 *   1. pendingArchiveRequests   — deal_approval_requests WHERE type='deal_archive' AND status='pending'
 *   2. pendingStageMoveRequests — deal_approval_requests WHERE type='deal_stage_move' AND status='pending'
 *   3. pendingExtensionRequests — deal_assignment_extension_requests WHERE status IN ('pending_review','clarification_requested')
 *   4. commissionReviewQueue    — leads WHERE stage IN ('signed','paid') AND commission_status='pending'
 *   5. stalledDeals             — leads WHERE status IN ('active','expiring') AND updated_at < threshold
 *
 * All indexes use CONCURRENTLY so they can be built online without locking.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // ── 1 & 2: deal_approval_requests — covers both archive and stage-move pending queries ──
        // Query pattern: WHERE tenant_id=? AND type IN ('deal_archive','deal_stage_move') AND status='pending'
        // The existing (tenant_id, deal_id, type, status) index does not help for tenant-wide scans.
        // A partial index on (tenant_id, type, status='pending') is more selective and smaller.
        try {
            DB::statement("
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_dar_tenant_type_pending
                ON deal_approval_requests (tenant_id, type)
                WHERE status = 'pending'
            ");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('idx_dar_tenant_type_pending failed', ['error' => $e->getMessage()]);
        }

        // ── 3: deal_assignment_extension_requests — covers pendingExtensionRequests query ──
        // Query pattern: WHERE tenant_id=? AND status IN ('pending_review','clarification_requested')
        try {
            DB::statement("
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_daer_tenant_pending_statuses
                ON deal_assignment_extension_requests (tenant_id, status)
                WHERE status IN ('pending_review', 'clarification_requested')
            ");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('idx_daer_tenant_pending_statuses failed', ['error' => $e->getMessage()]);
        }

        // ── 4: leads — commissionReviewQueue ──
        // Query pattern: WHERE tenant_id=? AND stage IN ('signed','paid') AND commission_status='pending' AND deleted_at IS NULL
        try {
            DB::statement("
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_commission_review_queue
                ON leads (tenant_id, stage, updated_at)
                WHERE commission_status = 'pending'
                  AND stage IN ('signed', 'paid')
                  AND deleted_at IS NULL
            ");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('idx_leads_commission_review_queue failed', ['error' => $e->getMessage()]);
        }

        // ── 5: leads — stalledDeals ──
        // Query pattern: WHERE tenant_id=? AND status IN ('active','expiring') AND updated_at < threshold AND deleted_at IS NULL
        // The existing idx_leads_tenant_status_active covers (tenant_id, status) WHERE deleted_at IS NULL
        // but does NOT include updated_at — Postgres must re-filter updated_at in a heap scan.
        // Adding updated_at to the index allows index-only evaluation of the stale threshold.
        try {
            DB::statement("
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_stalled_deals
                ON leads (tenant_id, updated_at)
                WHERE status IN ('active', 'expiring')
                  AND deleted_at IS NULL
                  AND stage NOT IN ('paid', 'archived')
            ");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('idx_leads_stalled_deals failed', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        foreach ([
            'idx_dar_tenant_type_pending',
            'idx_daer_tenant_pending_statuses',
            'idx_leads_commission_review_queue',
            'idx_leads_stalled_deals',
        ] as $idx) {
            try {
                DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$idx}");
            } catch (\Throwable) {}
        }
    }
};
