<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::unprepared('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_deal_comments_deal_tenant
            ON deal_comments (deal_id, tenant_id)
            WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::unprepared('DROP INDEX CONCURRENTLY IF EXISTS idx_deal_comments_deal_tenant');
    }
};
