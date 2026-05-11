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
            CREATE TABLE IF NOT EXISTS message_threads (
                id                    TEXT        PRIMARY KEY DEFAULT gen_random_uuid()::TEXT,
                tenant_id             TEXT        NOT NULL,
                reseller_id           TEXT        NOT NULL,
                deal_id               TEXT,
                status                TEXT        NOT NULL DEFAULT 'open',
                last_message_at       TIMESTAMPTZ,
                last_message_preview  TEXT,
                admin_unread          INT         NOT NULL DEFAULT 0,
                reseller_unread       INT         NOT NULL DEFAULT 0,
                created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                UNIQUE(tenant_id, reseller_id)
            )
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS thread_messages (
                id          TEXT        PRIMARY KEY DEFAULT gen_random_uuid()::TEXT,
                thread_id   TEXT        NOT NULL REFERENCES message_threads(id) ON DELETE CASCADE,
                tenant_id   TEXT        NOT NULL,
                sender_type TEXT        NOT NULL CHECK (sender_type IN ('admin','reseller','system')),
                sender_id   TEXT,
                sender_name TEXT,
                body        TEXT        NOT NULL,
                is_read     BOOLEAN     NOT NULL DEFAULT FALSE,
                read_at     TIMESTAMPTZ,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");

        DB::statement("CREATE INDEX IF NOT EXISTS idx_message_threads_tenant    ON message_threads(tenant_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_message_threads_reseller  ON message_threads(reseller_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_message_threads_deal_id   ON message_threads(deal_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_message_threads_status    ON message_threads(status)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_message_threads_last_msg  ON message_threads(last_message_at DESC)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_thread_messages_thread    ON thread_messages(thread_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_thread_messages_tenant    ON thread_messages(tenant_id)");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("DROP TABLE IF EXISTS thread_messages");
        DB::statement("DROP TABLE IF EXISTS message_threads");
    }
};
