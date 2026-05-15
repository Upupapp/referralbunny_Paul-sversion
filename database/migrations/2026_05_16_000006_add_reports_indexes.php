<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // ExportController::index — WHERE tenant_id=? AND status IN(...)
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_export_requests_tenant_status
            ON export_requests(tenant_id, status)
        ");

        // ExportController list — WHERE tenant_id=? AND requester_type=? AND requester_id=?
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_export_requests_tenant_requester
            ON export_requests(tenant_id, requester_type, requester_id)
        ");

        // CleanupExpiredExportsCommand — WHERE status IN(...) AND file_expires_at < now()
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_export_requests_file_expires
            ON export_requests(status, file_expires_at)
            WHERE file_expires_at IS NOT NULL
        ");

        // TenantCommissionController — WHERE tenant_id=? AND commission_status=?
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_leads_tenant_commission_status
            ON leads(tenant_id, commission_status)
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void {}
};
