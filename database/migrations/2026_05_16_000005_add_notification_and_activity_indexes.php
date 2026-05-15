<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // ── notifications: polled every 30–60s by every logged-in user ──────
        // Primary fetch path: queryForUser() + ORDER BY created_at DESC
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_notifications_notifiable_created
            ON notifications(notifiable_type, notifiable_id, created_at DESC)
            WHERE archived_at IS NULL
        ");

        // Unread count path: is_read=false filter
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_notifications_notifiable_unread
            ON notifications(notifiable_type, notifiable_id, is_read)
            WHERE is_read = false AND archived_at IS NULL
        ");

        // Deduplication key lookup on every dispatch call
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_notifications_dedup_key
            ON notifications(deduplication_key)
            WHERE deduplication_key IS NOT NULL
        ");

        // ── activity_logs: queried by CriticalActionService on every dashboard load ──
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_activity_logs_tenant_created
            ON activity_logs(tenant_id, created_at DESC)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_activity_logs_entity
            ON activity_logs(tenant_id, entity, entity_id)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_activity_logs_action
            ON activity_logs(tenant_id, action, created_at DESC)
        ");

        // ── priority CHECK constraint: expand to include 'normal' and 'urgent' ──
        // The codebase uses 'normal' and 'urgent' but the original constraint
        // only allowed 'low','medium','high','critical'.
        DB::statement("
            ALTER TABLE notifications
            DROP CONSTRAINT IF EXISTS notifications_priority_check
        ");
        DB::statement("
            ALTER TABLE notifications
            ADD CONSTRAINT notifications_priority_check
            CHECK (priority IN ('low','normal','medium','high','urgent','critical'))
        ");
    }

    public function down(): void {}
};
