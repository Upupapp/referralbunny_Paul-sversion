<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Partial index covers the exact billingIssues() query:
            // WHERE tenant_id = ? AND status = 'failed' AND retry_count < 3 ORDER BY created_at DESC LIMIT 1
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_payments_tenant_failed
                ON payments (tenant_id, created_at DESC)
                WHERE status = \'failed\' AND retry_count < 3');

            // Covers the resellerFailedExports() query:
            // WHERE tenant_id = ? AND requester_type = 'reseller' AND requester_id = ? AND status = 'failed'
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_export_requests_reseller_failed
                ON export_requests (tenant_id, requester_type, requester_id, updated_at DESC)
                WHERE status = \'failed\'');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_payments_tenant_failed');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_export_requests_reseller_failed');
        }
    }
};
