<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // idx_notifications_notifiable_tenant (added in 000003) supersedes this index:
        // both share the same leading columns and WHERE predicate; this one lacks tenant_id
        // and adds unnecessary write overhead on every notifications insert/update.
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_notifications_notifiable_unread');
    }

    public function down(): void
    {
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_notifications_notifiable_unread
            ON notifications (notifiable_type, notifiable_id, is_read)
            WHERE is_read = false AND archived_at IS NULL
        ');
    }
};
