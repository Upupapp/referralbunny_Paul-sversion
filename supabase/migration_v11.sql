-- ============================================================
-- migration_v11.sql : Agreement Files & Acknowledgment System
-- Run after migration_v10.sql
-- ============================================================

-- ── Agreement file templates (admin-managed per tenant) ──────────────────
CREATE TABLE IF NOT EXISTS reseller_agreement_files (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    label           VARCHAR(100) NOT NULL,
    description     TEXT,
    file_url        TEXT,
    is_required     BOOLEAN NOT NULL DEFAULT true,
    version         VARCHAR(20) DEFAULT '1.0',
    effective_date  DATE,
    display_order   INTEGER NOT NULL DEFAULT 0,
    is_active       BOOLEAN NOT NULL DEFAULT true,
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ── Per-reseller acknowledgment records ──────────────────────────────────
CREATE TABLE IF NOT EXISTS reseller_agreement_acknowledgments (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    reseller_id         UUID NOT NULL REFERENCES resellers(id) ON DELETE CASCADE,
    agreement_file_id   UUID NOT NULL REFERENCES reseller_agreement_files(id) ON DELETE CASCADE,
    agreed_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    agreed_by_name      VARCHAR(255),
    ip_address          VARCHAR(45),
    created_at          TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(reseller_id, agreement_file_id)
);

CREATE INDEX IF NOT EXISTS idx_agreement_files_tenant   ON reseller_agreement_files(tenant_id, is_active);
CREATE INDEX IF NOT EXISTS idx_agreement_acks_reseller  ON reseller_agreement_acknowledgments(reseller_id);
CREATE INDEX IF NOT EXISTS idx_agreement_acks_file      ON reseller_agreement_acknowledgments(agreement_file_id);
CREATE INDEX IF NOT EXISTS idx_agreement_acks_tenant    ON reseller_agreement_acknowledgments(tenant_id);
