-- migration_v49: critical_action_dismissals table
-- Stores per-user dismissals of Critical Actions panel items.
-- fingerprint = MD5 of type:source:related_type:related_id
-- expires_at = null means permanent; datetime means auto-resurface after that date

CREATE TABLE IF NOT EXISTS critical_action_dismissals (
    id          BIGSERIAL PRIMARY KEY,
    tenant_id   TEXT NOT NULL,
    user_id     TEXT NOT NULL,
    user_type   TEXT NOT NULL DEFAULT 'tenant_user',
    fingerprint TEXT NOT NULL,
    action_type TEXT NOT NULL DEFAULT '',
    dismissed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at  TIMESTAMPTZ NULL,
    CONSTRAINT ca_dismissals_unique
        UNIQUE (tenant_id, user_id, user_type, fingerprint)
);

-- Covers the primary query pattern: filter by tenant + user + type
CREATE INDEX IF NOT EXISTS idx_ca_dismissals_tenant_user
    ON critical_action_dismissals (tenant_id, user_id, user_type);
