<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // dashboardCounts pending-invites query: WHERE tenant_id=? AND status='pending' AND expires_at > ?
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tenant_invitations_tenant_status_expiry
            ON tenant_invitations (tenant_id, status, expires_at)
        ');

        // dashboardCounts import-warnings query: WHERE tenant_id=? AND status=? AND created_at > ?
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_import_batches_tenant_status_created
            ON import_batches (tenant_id, status, created_at DESC)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_tenant_invitations_tenant_status_expiry');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_import_batches_tenant_status_created');
    }
};
