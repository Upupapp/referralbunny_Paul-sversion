-- migration_v25.sql
-- Partner messaging: partner_threads + partner_messages

-- partner_threads: one thread per (deal, partner) pair
CREATE TABLE IF NOT EXISTS partner_threads (
    id                   UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id            TEXT        NOT NULL,
    deal_id              TEXT        NOT NULL,
    partner_id           UUID        NOT NULL REFERENCES partner_users(id) ON DELETE CASCADE,
    reseller_id          UUID        REFERENCES resellers(id) ON DELETE SET NULL,
    last_message_at      TIMESTAMPTZ,
    last_message_preview TEXT,
    partner_unread       INT         NOT NULL DEFAULT 0,
    reseller_unread      INT         NOT NULL DEFAULT 0,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_pthreads_tenant   ON partner_threads(tenant_id);
CREATE INDEX IF NOT EXISTS idx_pthreads_deal     ON partner_threads(deal_id);
CREATE INDEX IF NOT EXISTS idx_pthreads_partner  ON partner_threads(partner_id);
CREATE INDEX IF NOT EXISTS idx_pthreads_reseller ON partner_threads(reseller_id);

-- partner_messages: messages in a partner thread
CREATE TABLE IF NOT EXISTS partner_messages (
    id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    thread_id   UUID        NOT NULL REFERENCES partner_threads(id) ON DELETE CASCADE,
    tenant_id   TEXT        NOT NULL,
    deal_id     TEXT        NOT NULL,
    sender_type VARCHAR(20) NOT NULL CHECK (sender_type IN ('partner','reseller')),
    sender_id   TEXT        NOT NULL,
    sender_name TEXT        NOT NULL,
    body        TEXT        NOT NULL,
    is_read     BOOLEAN     NOT NULL DEFAULT false,
    read_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_pmsgs_thread  ON partner_messages(thread_id);
CREATE INDEX IF NOT EXISTS idx_pmsgs_tenant  ON partner_messages(tenant_id);
CREATE INDEX IF NOT EXISTS idx_pmsgs_deal    ON partner_messages(deal_id);

ALTER TABLE partner_threads  DISABLE ROW LEVEL SECURITY;
ALTER TABLE partner_messages DISABLE ROW LEVEL SECURITY;
