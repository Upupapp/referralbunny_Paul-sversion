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
            CREATE TABLE IF NOT EXISTS partner_threads (
                id                    TEXT        PRIMARY KEY DEFAULT gen_random_uuid()::TEXT,
                tenant_id             TEXT        NOT NULL,
                deal_id               TEXT,
                partner_id            TEXT        NOT NULL,
                reseller_id           TEXT,
                last_message_at       TIMESTAMPTZ,
                last_message_preview  TEXT,
                partner_unread        INT         NOT NULL DEFAULT 0,
                reseller_unread       INT         NOT NULL DEFAULT 0,
                created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at            TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS partner_messages (
                id          TEXT        PRIMARY KEY DEFAULT gen_random_uuid()::TEXT,
                thread_id   TEXT        NOT NULL REFERENCES partner_threads(id) ON DELETE CASCADE,
                tenant_id   TEXT        NOT NULL,
                deal_id     TEXT,
                sender_type TEXT        NOT NULL CHECK (sender_type IN ('partner','reseller','admin','system')),
                sender_id   TEXT,
                sender_name TEXT,
                body        TEXT        NOT NULL,
                is_read     BOOLEAN     NOT NULL DEFAULT FALSE,
                read_at     TIMESTAMPTZ,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");

        DB::statement("CREATE INDEX IF NOT EXISTS idx_partner_threads_tenant    ON partner_threads(tenant_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_partner_threads_partner   ON partner_threads(partner_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_partner_threads_deal      ON partner_threads(deal_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_partner_messages_thread   ON partner_messages(thread_id)");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement("DROP TABLE IF EXISTS partner_messages");
        DB::statement("DROP TABLE IF EXISTS partner_threads");
    }
};
