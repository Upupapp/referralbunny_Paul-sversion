<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Covers dashboardCounts 'missing_referrer' query:
        // WHERE tenant_id=? AND deleted_at IS NULL AND (reseller_name IS NULL OR reseller_name='') AND status IN ('active','expiring')
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_missing_referrer_partial
            ON leads (tenant_id, status)
            WHERE deleted_at IS NULL
              AND (reseller_name IS NULL OR reseller_name = '')
              AND status IN ('active', 'expiring')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_leads_missing_referrer_partial');
    }
};
