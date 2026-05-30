<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Covers LeadController::index() default query:
        //   WHERE tenant_id = ? AND status != 'archived' AND deleted_at IS NULL
        //   ORDER BY created_at DESC LIMIT 200
        // Single backward index scan — no extra sort step at any tenant size.
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_tenant_active
            ON leads (tenant_id, created_at DESC)
            WHERE status <> 'archived'
              AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_leads_tenant_active');
    }
};
