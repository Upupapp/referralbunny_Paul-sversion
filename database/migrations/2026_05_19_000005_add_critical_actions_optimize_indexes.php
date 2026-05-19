<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Critical Actions OPTIMIZE — missing indexes for all 19+ source queries.
 *
 * Sources addressed:
 *  - stalledDeals             → leads(tenant_id, status, updated_at) WHERE deleted_at IS NULL
 *  - commissionReviewQueue    → leads(tenant_id, stage, commission_status) WHERE deleted_at IS NULL
 *  - expiredDeals             → leads(tenant_id, status, updated_at) WHERE deleted_at IS NULL
 *  - pendingExtensionRequests → deal_assignment_extension_requests(tenant_id, status, created_at)
 *  - failedRollbacks          → import_rollbacks(tenant_id, status, created_at)
 *  - staleGoogleCalendar      → google_calendar_integrations(tenant_id, is_active)
 *  - recentLeadHistory        → lead_history(tenant_id, type, created_at) WHERE NOT audit-only types
 *  - recentReferrerAmtChanges → lead_history(tenant_id, action, created_at)
 *  - pendingArchive/StageMove → deal_approval_requests(tenant_id, type, status)
 *  - resellerOverdueTasks     → tasks(tenant_id, assigned_to_type, assigned_to_id, due_at)
 *  - openRequestFormTasks     → tasks(tenant_id, status, category) WHERE deleted_at IS NULL
 *  - pendingInvites/referrers → resellers(tenant_id, status, created_at) WHERE deleted_at IS NULL
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // ── leads: stalledDeals + expiredDeals need updated_at range filter ───────
        // stalledDeals: WHERE tenant_id=? AND status IN ('active','expiring') AND updated_at < ? AND deleted_at IS NULL
        // expiredDeals:  WHERE tenant_id=? AND status='expired' AND updated_at > ? AND deleted_at IS NULL
        // Composite (tenant_id, status, updated_at) with partial WHERE deleted_at IS NULL
        // covers both without a second index.
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_tenant_status_updated
            ON leads (tenant_id, status, updated_at)
            WHERE deleted_at IS NULL
        ");

        // ── leads: commissionReviewQueue ─────────────────────────────────────────
        // WHERE tenant_id=? AND stage IN ('signed','paid') AND commission_status='pending' AND deleted_at IS NULL
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_tenant_stage_commission_status
            ON leads (tenant_id, stage, commission_status)
            WHERE deleted_at IS NULL
        ");

        // ── deal_assignment_extension_requests: pendingExtensionRequests ─────────
        // WHERE tenant_id=? AND status IN ('pending_review','clarification_requested')
        // The table already has an index on (tenant_id, deal_id, type, status) via create migration
        // but no standalone (tenant_id, status, created_at) for the admin list query.
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_deal_ext_requests_tenant_status_created
            ON deal_assignment_extension_requests (tenant_id, status, created_at)
        ");

        // ── import_rollbacks: failedRollbacks ─────────────────────────────────────
        // WHERE tenant_id=? AND status IN ('failed','completed_with_warnings') AND created_at > ?
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_import_rollbacks_tenant_status_created
            ON import_rollbacks (tenant_id, status, created_at DESC)
        ");

        // ── google_calendar_integrations: staleGoogleCalendarIntegrations ─────────
        // WHERE tenant_id=? AND is_active=true AND (token_expires_at < ? OR last_synced_at < ?)
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_gcal_integrations_tenant_active
            ON google_calendar_integrations (tenant_id, is_active, token_expires_at, last_synced_at)
            WHERE is_active = true
        ");

        // ── lead_history: recentLeadHistory ──────────────────────────────────────
        // JOIN leads l ON l.id = h.lead_id WHERE l.tenant_id=? AND h.type IN (...) AND h.created_at > ?
        // tenant_id on lead_history allows the planner to filter by tenant without going through leads.
        // (tenant_id, type, created_at) partial index covers type IN ('stage','assignment','commission')
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_lead_history_tenant_type_created
            ON lead_history (tenant_id, type, created_at DESC)
            WHERE type IN ('stage', 'assignment', 'commission', 'financial')
        ");

        // ── lead_history: recentReferrerAmountChanges ─────────────────────────────
        // WHERE tenant_id=? AND action LIKE 'amount updated by referrer%' AND created_at >= ?
        // AND category IN ('financial','commission')
        // A btree index on (tenant_id, action) lets PostgreSQL do a prefix scan on action.
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_lead_history_tenant_action_created
            ON lead_history (tenant_id, action, created_at DESC)
        ");

        // ── deal_approval_requests: pendingArchiveRequests + pendingStageMoveRequests ──
        // WHERE tenant_id=? AND type='deal_archive' AND status='pending'
        // WHERE tenant_id=? AND type='deal_stage_move' AND status='pending'
        // The existing (tenant_id, status) index exists but adding type makes it a covering scan.
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_deal_approval_tenant_type_status
            ON deal_approval_requests (tenant_id, type, status, created_at)
        ");

        // ── tasks: resellerOverdueTasks ────────────────────────────────────────────
        // WHERE tenant_id=? AND assigned_to_type='reseller' AND assigned_to_id=? AND due_at < ? AND deleted_at IS NULL
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tasks_tenant_assignee_due
            ON tasks (tenant_id, assigned_to_type, assigned_to_id, due_at)
            WHERE deleted_at IS NULL
        ");

        // ── tasks: openRequestFormTasks ────────────────────────────────────────────
        // WHERE tenant_id=? AND status='open' AND category='request_form' AND deleted_at IS NULL
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tasks_tenant_status_category
            ON tasks (tenant_id, status, category)
            WHERE deleted_at IS NULL
        ");

        // ── resellers: pendingInvites — expiring referrer invite window ────────────
        // WHERE tenant_id=? AND status='invited' AND created_at < now()-83d AND created_at > now()-90d AND deleted_at IS NULL
        // The existing idx_resellers_tenant_status_created covers (tenant_id, status, created_at DESC)
        // but it is NOT partial. Add a partial one for the invited + not-deleted pattern.
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_resellers_tenant_invited_created
            ON resellers (tenant_id, status, created_at DESC)
            WHERE deleted_at IS NULL AND status = 'invited'
        ");
    }

    public function down(): void
    {
        $indexes = [
            'idx_leads_tenant_status_updated',
            'idx_leads_tenant_stage_commission_status',
            'idx_deal_ext_requests_tenant_status_created',
            'idx_import_rollbacks_tenant_status_created',
            'idx_gcal_integrations_tenant_active',
            'idx_lead_history_tenant_type_created',
            'idx_lead_history_tenant_action_created',
            'idx_deal_approval_tenant_type_status',
            'idx_tasks_tenant_assignee_due',
            'idx_tasks_tenant_status_category',
            'idx_resellers_tenant_invited_created',
        ];

        foreach ($indexes as $idx) {
            try {
                DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$idx}");
            } catch (\Throwable) {}
        }
    }
};
