-- ============================================================
-- migration_v23.sql : Smart messaging reminder system
-- Run after migration_v22.sql
-- ============================================================

-- ── 1. Extend message_threads ─────────────────────────────────
ALTER TABLE message_threads
  ADD COLUMN IF NOT EXISTS deal_id  TEXT,
  ADD COLUMN IF NOT EXISTS status   TEXT NOT NULL DEFAULT 'open'
    CHECK (status IN ('open','resolved','archived'));

CREATE INDEX IF NOT EXISTS idx_message_threads_deal_id ON message_threads(deal_id);
CREATE INDEX IF NOT EXISTS idx_message_threads_status  ON message_threads(status);
CREATE INDEX IF NOT EXISTS idx_message_threads_last_msg ON message_threads(last_message_at DESC);

-- ── 2. Message reminder states ────────────────────────────────
CREATE TABLE IF NOT EXISTS message_reminder_states (
    id                UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    notifiable_type   TEXT        NOT NULL CHECK (notifiable_type IN ('tenant_admin','reseller','super_admin')),
    notifiable_id     TEXT        NOT NULL,
    tenant_id         TEXT,
    reminder_type     TEXT        NOT NULL,
    thread_id         UUID        REFERENCES message_threads(id) ON DELETE CASCADE,
    deal_id           TEXT,
    deduplication_key TEXT        NOT NULL,
    status            TEXT        NOT NULL DEFAULT 'active'
                      CHECK (status IN ('active','snoozed','resolved','dismissed')),
    priority          TEXT        NOT NULL DEFAULT 'normal'
                      CHECK (priority IN ('low','normal','high','urgent')),
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
);

CREATE INDEX IF NOT EXISTS idx_reminder_states_notifiable  ON message_reminder_states(notifiable_type, notifiable_id);
CREATE INDEX IF NOT EXISTS idx_reminder_states_status      ON message_reminder_states(status);
CREATE INDEX IF NOT EXISTS idx_reminder_states_next_remind ON message_reminder_states(next_remind_at);
CREATE INDEX IF NOT EXISTS idx_reminder_states_thread      ON message_reminder_states(thread_id);

-- ── 3. Message drafts ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS message_drafts (
    id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    thread_id   UUID        NOT NULL REFERENCES message_threads(id) ON DELETE CASCADE,
    tenant_id   TEXT        NOT NULL,
    author_type TEXT        NOT NULL CHECK (author_type IN ('admin','reseller')),
    author_id   TEXT        NOT NULL,
    body        TEXT        NOT NULL DEFAULT '',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(thread_id, author_type, author_id)
);

CREATE INDEX IF NOT EXISTS idx_message_drafts_author ON message_drafts(author_type, author_id);

-- ── 4. Disable RLS ────────────────────────────────────────────
ALTER TABLE message_reminder_states DISABLE ROW LEVEL SECURITY;
ALTER TABLE message_drafts          DISABLE ROW LEVEL SECURITY;
