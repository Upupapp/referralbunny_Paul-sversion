<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // CriticalActionService queries resellers filtered by (tenant_id, deleted_at IS NULL)
        // and various status values. A composite partial index covers the hot path.
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_resellers_tenant_active
            ON resellers (tenant_id, status)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_resellers_tenant_active');
    }
};
