<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            CREATE TABLE IF NOT EXISTS message_reminder_states (
                id                TEXT        PRIMARY KEY DEFAULT gen_random_uuid()::TEXT,
                notifiable_type   TEXT        NOT NULL,
                notifiable_id     TEXT        NOT NULL,
                tenant_id         TEXT,
                reminder_type     TEXT        NOT NULL,
                thread_id         TEXT,
                deal_id           TEXT,
                deduplication_key TEXT        NOT NULL,
                status            TEXT        NOT NULL DEFAULT 'active',
                priority          TEXT        NOT NULL DEFAULT 'normal',
                title             TEXT        NOT NULL,
                body              TEXT        NOT NULL,
                action_url        TEXT,
                metadata          JSONB,
                reminder_count    INT         NOT NULL DEFAULT 1,
                next_remind_at    TIMESTAMPTZ,
                snoozed_until     TIMESTAMPTZ,
                resolved_at       TIMESTAMPTZ,
                dismissed_at      TIMESTAMPTZ,
                created_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                UNIQUE(deduplication_key)
            )
        ");

        DB::statement("CREATE INDEX IF NOT EXISTS idx_reminder_states_notifiable   ON message_reminder_states(notifiable_type, notifiable_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_reminder_states_status       ON message_reminder_states(status)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_reminder_states_next_remind  ON message_reminder_states(next_remind_at)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_reminder_states_thread       ON message_reminder_states(thread_id)");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("DROP TABLE IF EXISTS message_reminder_states");
    }
};
