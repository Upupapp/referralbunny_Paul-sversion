<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Supports: WHERE tenant_id = ? AND created_at > ? (new deals since last session + daily briefing)
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_tenant_created
            ON leads(tenant_id, created_at DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_leads_tenant_created');
    }
};
