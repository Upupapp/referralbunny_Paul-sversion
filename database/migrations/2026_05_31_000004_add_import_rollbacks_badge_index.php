<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Add tenant_id if the 2026_05_10_000030 migration silently skipped it
        if (Schema::hasTable('import_rollbacks') && !Schema::hasColumn('import_rollbacks', 'tenant_id')) {
            Schema::table('import_rollbacks', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->after('import_batch_id');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            // Covers badgeCount() block 12 query:
            // WHERE tenant_id = ? AND status IN ('failed','completed_with_warnings') AND created_at > now()-14d
            DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_import_rollbacks_tenant_status_created
                ON import_rollbacks (tenant_id, status, created_at DESC)
                WHERE status IN ('failed', 'completed_with_warnings')");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_import_rollbacks_tenant_status_created');
        }
    }
};
