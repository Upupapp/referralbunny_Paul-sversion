<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_notifications_notifiable_tenant
            ON notifications (notifiable_type, notifiable_id, tenant_id)
            WHERE is_read = false AND archived_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_notifications_notifiable_tenant');
    }
};
