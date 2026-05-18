<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_lead_history_tenant_lead_created
            ON lead_history(tenant_id, lead_id, created_at DESC)');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_activity_logs_tenant_entity_created
            ON activity_logs(tenant_id, entity, entity_id, created_at DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_lead_history_tenant_lead_created');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_activity_logs_tenant_entity_created');
    }
};
