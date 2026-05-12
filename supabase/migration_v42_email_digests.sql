-- Migration v42: Email Digest Queue
-- Stores pending email notifications for batching.
-- Same recipient + topic within the window_hours period are merged into one summary email.
-- A scheduled command (email:send-digests) processes this table and sends consolidated emails.

CREATE TABLE IF NOT EXISTS email_digests (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       TEXT        REFERENCES tenants(id) ON DELETE CASCADE,
    recipient_email TEXT        NOT NULL,
    recipient_name  TEXT,
    topic           TEXT        NOT NULL,   -- e.g. 'deal_created', 'stage_changed', 'message_received'
    topic_label     TEXT,                   -- Human-readable: 'Deal Created', 'Stage Changed'
    items           JSONB       NOT NULL DEFAULT '[]',
    window_hours    SMALLINT    NOT NULL DEFAULT 1,
    scheduled_at    TIMESTAMPTZ NOT NULL,   -- when to send (window_hours after first item)
    sent_at         TIMESTAMPTZ,
    failed_at       TIMESTAMPTZ,
    retry_count     SMALLINT    NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_email_digests_pending
    ON email_digests(recipient_email, topic, scheduled_at)
    WHERE sent_at IS NULL AND failed_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_email_digests_scheduled
    ON email_digests(scheduled_at)
    WHERE sent_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_email_digests_tenant
    ON email_digests(tenant_id, created_at);
