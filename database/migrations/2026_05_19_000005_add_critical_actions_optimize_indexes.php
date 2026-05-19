<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Critical Actions OPTIMIZE — missing indexes for source queries.
 * Each statement is wrapped independently so one unsupported column
 * does not abort the remaining indexes.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $indexes = [
            // stalledDeals + expiredDeals — need updated_at range filter
            'idx_leads_tenant_status_updated' => "
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_tenant_status_updated
                ON leads (tenant_id, status, updated_at)
                WHERE deleted_at IS NULL
            ",

            // commissionReviewQueue
            'idx_leads_tenant_stage_commission_status' => "
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_tenant_stage_commission_status
                ON leads (tenant_id, stage, commission_status)
                WHERE deleted_at IS NULL
            ",

            // pendingExtensionRequests
            'idx_deal_ext_requests_tenant_status_created' => "
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_deal_ext_requests_tenant_status_created
                ON deal_assignment_extension_requests (tenant_id, status, created_at)
            ",

            // staleGoogleCalendarIntegrations
            'idx_gcal_integrations_tenant_active' => "
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_gcal_integrations_tenant_active
                ON google_calendar_integrations (tenant_id, is_active, token_expires_at, last_synced_at)
                WHERE is_active = true
            ",

            // recentLeadHistory
            'idx_lead_history_tenant_type_created' => "
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_lead_history_tenant_type_created
                ON lead_history (tenant_id, type, created_at DESC)
                WHERE type IN ('stage', 'assignment', 'commission', 'financial')
            ",

            // recentReferrerAmountChanges
            'idx_lead_history_tenant_action_created' => "
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_lead_history_tenant_action_created
                ON lead_history (tenant_id, action, created_at DESC)
            ",

            // pendingArchiveRequests + pendingStageMoveRequests
            'idx_deal_approval_tenant_type_status' => "
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_deal_approval_tenant_type_status
                ON deal_approval_requests (tenant_id, type, status, created_at)
            ",

            // resellerOverdueTasks
            'idx_tasks_tenant_assignee_due' => "
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tasks_tenant_assignee_due
                ON tasks (tenant_id, assigned_to_type, assigned_to_id, due_at)
                WHERE deleted_at IS NULL
            ",

            // openRequestFormTasks
            'idx_tasks_tenant_status_category' => "
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tasks_tenant_status_category
                ON tasks (tenant_id, status, category)
                WHERE deleted_at IS NULL
            ",

            // pendingInvites — expiring referrer invite window
            'idx_resellers_tenant_invited_created' => "
                CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_resellers_tenant_invited_created
                ON resellers (tenant_id, status, created_at DESC)
                WHERE deleted_at IS NULL AND status = 'invited'
            ",
        ];

        foreach ($indexes as $name => $sql) {
            try {
                DB::statement($sql);
            } catch (\Throwable $e) {
                Log::warning("[Migration] Skipped index {$name}: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            'idx_leads_tenant_status_updated',
            'idx_leads_tenant_stage_commission_status',
            'idx_deal_ext_requests_tenant_status_created',
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
