-- Migration v47: Partner Messaging Tables
-- Partner-to-Referrer deal-scoped messaging system.

CREATE TABLE IF NOT EXISTS partner_threads (
    id                    UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id             VARCHAR      NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    deal_id               UUID         REFERENCES leads(id) ON DELETE SET NULL,
    partner_id            UUID         NOT NULL,
    reseller_id           UUID,
    last_message_at       TIMESTAMPTZ,
    last_message_preview  TEXT,
    partner_unread        INT          NOT NULL DEFAULT 0,
    reseller_unread       INT          NOT NULL DEFAULT 0,
    created_at            TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS partner_messages (
    id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    thread_id   UUID         NOT NULL REFERENCES partner_threads(id) ON DELETE CASCADE,
    tenant_id   VARCHAR      NOT NULL,
    deal_id     UUID,
    sender_type VARCHAR(20)  NOT NULL CHECK (sender_type IN ('partner','reseller','admin','system')),
    sender_id   TEXT,
    sender_name TEXT,
    body        TEXT         NOT NULL,
    is_read     BOOLEAN      NOT NULL DEFAULT FALSE,
    read_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_partner_threads_tenant  ON partner_threads(tenant_id);
CREATE INDEX IF NOT EXISTS idx_partner_threads_partner ON partner_threads(partner_id);
CREATE INDEX IF NOT EXISTS idx_partner_threads_deal    ON partner_threads(deal_id);
CREATE INDEX IF NOT EXISTS idx_partner_messages_thread ON partner_messages(thread_id);
