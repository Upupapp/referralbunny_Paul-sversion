-- ============================================================
-- migration_v18.sql : Email Logs + Email Preferences
-- Run after migration_v17.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS email_logs (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email_key       VARCHAR(255) NOT NULL,      -- dedup key e.g. daily_briefing.tenant_x.2026-05-05
    recipient_email VARCHAR(255) NOT NULL,
    recipient_type  VARCHAR(50)  NOT NULL,       -- super_admin | tenant_admin | reseller
    recipient_id    TEXT,
    tenant_id       TEXT REFERENCES tenants(id) ON DELETE SET NULL,
    subject         VARCHAR(500) NOT NULL,
    status          VARCHAR(20)  NOT NULL DEFAULT 'pending',  -- pending | sent | failed | skipped
    sent_at         TIMESTAMPTZ,
    failed_at       TIMESTAMPTZ,
    error_message   TEXT,
    metadata        JSONB DEFAULT '{}',
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_email_logs_key       ON email_logs(email_key);
CREATE INDEX IF NOT EXISTS idx_email_logs_recipient ON email_logs(recipient_email);
CREATE INDEX IF NOT EXISTS idx_email_logs_tenant    ON email_logs(tenant_id);
CREATE INDEX IF NOT EXISTS idx_email_logs_status    ON email_logs(status);
CREATE INDEX IF NOT EXISTS idx_email_logs_created   ON email_logs(created_at DESC);

-- ── Email preferences (future-ready, defaults allow all) ────
CREATE TABLE IF NOT EXISTS email_preferences (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    recipient_type          VARCHAR(50)  NOT NULL,
    recipient_id            TEXT         NOT NULL,
    tenant_id               TEXT REFERENCES tenants(id) ON DELETE CASCADE,
    daily_briefing          BOOLEAN DEFAULT TRUE,
    deal_alerts             BOOLEAN DEFAULT TRUE,
    stage_warnings          BOOLEAN DEFAULT TRUE,
    expiry_alerts           BOOLEAN DEFAULT TRUE,
    commission_emails       BOOLEAN DEFAULT TRUE,
    reseller_activity       BOOLEAN DEFAULT TRUE,
    weekly_summary          BOOLEAN DEFAULT TRUE,
    created_at              TIMESTAMPTZ DEFAULT NOW(),
    updated_at              TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(recipient_type, recipient_id)
);
