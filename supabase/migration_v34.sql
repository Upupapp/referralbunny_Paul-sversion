-- ── Export Approval System (migration_v34) ─────────────────────────────────
-- Run after migration_v33.sql

-- export_requests: tracks all export requests and their approval/file status
CREATE TABLE IF NOT EXISTS export_requests (
    id                  UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           TEXT        NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,

    -- Requester
    requester_type      TEXT        NOT NULL,  -- 'tenant_user' | 'reseller'
    requester_id        UUID        NOT NULL,
    requester_role      TEXT        NOT NULL,  -- owner|admin|manager|member|viewer|referrer

    -- Export definition
    export_type         TEXT        NOT NULL,  -- deals|contacts|organizations|referrers|commissions|users|audit_logs|reports|messages|import_summary
    export_format       TEXT        NOT NULL   DEFAULT 'csv',  -- csv|xlsx
    export_scope        JSONB                  DEFAULT '{}',   -- filters, date_from, date_to, etc.
    export_fields       JSONB                  DEFAULT '[]',   -- selected field keys
    is_sensitive        BOOLEAN     NOT NULL   DEFAULT false,
    records_estimate    INT                    DEFAULT 0,

    -- Request
    reason              TEXT,
    status              TEXT        NOT NULL   DEFAULT 'pending',
    -- pending|approved|processing|ready|rejected|cancelled|expired|failed|direct_pending|direct_ready

    -- Approval
    approved_by         UUID,
    approved_at         TIMESTAMPTZ,
    rejected_by         UUID,
    rejected_at         TIMESTAMPTZ,
    rejection_reason    TEXT,

    -- File
    file_path           TEXT,
    file_name           TEXT,
    file_size           BIGINT                 DEFAULT 0,
    file_expires_at     TIMESTAMPTZ,
    downloaded_at       TIMESTAMPTZ,
    download_count      INT         NOT NULL   DEFAULT 0,

    -- Error
    error_message       TEXT,
    retry_count         INT         NOT NULL   DEFAULT 0,

    created_at          TIMESTAMPTZ NOT NULL   DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL   DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_export_requests_tenant    ON export_requests(tenant_id);
CREATE INDEX IF NOT EXISTS idx_export_requests_status    ON export_requests(tenant_id, status);
CREATE INDEX IF NOT EXISTS idx_export_requests_requester ON export_requests(requester_type, requester_id);
CREATE INDEX IF NOT EXISTS idx_export_requests_expires   ON export_requests(file_expires_at) WHERE file_expires_at IS NOT NULL;

ALTER TABLE export_requests DISABLE ROW LEVEL SECURITY;

-- Add export_settings to tenant_configs (JSONB, default empty → service fills defaults)
ALTER TABLE tenant_configs
    ADD COLUMN IF NOT EXISTS export_settings JSONB NOT NULL DEFAULT '{}';

-- Per-Referrer direct export capability grant
ALTER TABLE resellers
    ADD COLUMN IF NOT EXISTS can_export_own_data BOOLEAN NOT NULL DEFAULT false;
