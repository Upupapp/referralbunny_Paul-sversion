<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Covers the admin cache-bust query in HandleDealDeclined, HandleDealStageMoved,
        // and HandleCommissionStatusChanged listeners:
        //   WHERE tenant_id = ? AND status = 'active' AND role IN ('owner','admin','manager')
        // Eliminates in-memory filter on the existing single-column tenant_id index.
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tenant_memberships_tenant_active_role
            ON tenant_memberships (tenant_id, role)
            WHERE status = 'active'
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_tenant_memberships_tenant_active_role');
    }
};
