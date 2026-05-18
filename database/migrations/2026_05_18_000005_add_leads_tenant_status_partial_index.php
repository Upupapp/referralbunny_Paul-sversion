<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Covers WHERE tenant_id=? AND status=? AND deleted_at IS NULL
        // Used by TenantAdminController dashboard expiring-deals briefing query
        // and any other status-filtered lead lookups that exclude soft-deleted rows.
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_tenant_status_active
            ON leads (tenant_id, status)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_leads_tenant_status_active');
    }
};
