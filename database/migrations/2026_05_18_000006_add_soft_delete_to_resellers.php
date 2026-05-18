<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasColumn('resellers', 'deleted_at')) {
            DB::statement('ALTER TABLE resellers ADD COLUMN deleted_at TIMESTAMPTZ DEFAULT NULL');
        }

        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_resellers_tenant_deleted_at
            ON resellers (tenant_id, deleted_at)
            WHERE deleted_at IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_resellers_tenant_deleted_at');
        if (Schema::hasColumn('resellers', 'deleted_at')) {
            DB::statement('ALTER TABLE resellers DROP COLUMN deleted_at');
        }
    }
};
