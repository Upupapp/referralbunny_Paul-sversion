-- ============================================================
-- migration_v21.sql : In-app messaging (threads + messages)
-- Run after migration_v20.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS message_threads (
    id             UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id      TEXT        NOT NULL,
    reseller_id    TEXT        NOT NULL,
    last_message_at TIMESTAMPTZ,
    last_message_preview TEXT,
    admin_unread   INT         NOT NULL DEFAULT 0,
    reseller_unread INT        NOT NULL DEFAULT 0,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(tenant_id, reseller_id)
);

CREATE TABLE IF NOT EXISTS thread_messages (
    id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    thread_id   UUID        NOT NULL REFERENCES message_threads(id) ON DELETE CASCADE,
    tenant_id   TEXT        NOT NULL,
    sender_type TEXT        NOT NULL CHECK (sender_type IN ('admin','reseller','system')),
    sender_id   TEXT,
    sender_name TEXT,
    body        TEXT        NOT NULL,
    is_read     BOOLEAN     NOT NULL DEFAULT FALSE,
    read_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_message_threads_tenant   ON message_threads(tenant_id);
CREATE INDEX IF NOT EXISTS idx_message_threads_reseller ON message_threads(reseller_id);
CREATE INDEX IF NOT EXISTS idx_thread_messages_thread   ON thread_messages(thread_id);
CREATE INDEX IF NOT EXISTS idx_thread_messages_tenant   ON thread_messages(tenant_id);
