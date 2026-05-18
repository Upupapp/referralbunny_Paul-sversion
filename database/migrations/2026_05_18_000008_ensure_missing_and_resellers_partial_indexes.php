<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // idx_resellers_tenant_status_created was absent from production after
        // migration 2026_05_16_000002 — multi-statement up() caused silent partial
        // failure via the Supabase PgBouncer pooler. Re-create here with IF NOT EXISTS.
        // Covers: WHERE tenant_id=? AND status=? AND created_at > ? (briefing + CA queries)
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_resellers_tenant_status_created
            ON resellers (tenant_id, status, created_at DESC)
        ');

        // idx_deal_partner_splits_tenant_created — same silent-failure root cause.
        // Covers: WHERE tenant_id=? AND created_at > ? AND deleted_at IS NULL (CA newPartnersOnDeals)
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_deal_partner_splits_tenant_created
            ON deal_partner_splits (tenant_id, created_at DESC)
            WHERE deleted_at IS NULL
        ');

        // Partial index for resellers soft-delete queries.
        // Covers: WHERE tenant_id=? AND deleted_at IS NULL AND status IN (...)
        // Used by dealShow() referrer picker and referrers summary page.
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_resellers_tenant_status_active
            ON resellers (tenant_id, status)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_resellers_tenant_status_created');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_deal_partner_splits_tenant_created');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_resellers_tenant_status_active');
    }
};
