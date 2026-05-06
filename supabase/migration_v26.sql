-- migration_v26.sql
-- LGU IDS Deal Import: import_batches, import_batch_rows, pending_referrer_invites

-- ── LGU IDS Import Batches ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS import_batches (
    id                     UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id              TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    import_type            VARCHAR(50) NOT NULL DEFAULT 'lgu_ids_deals',
    file_name              TEXT,
    file_path              TEXT,
    imported_by_id         TEXT NOT NULL,
    imported_by_role       VARCHAR(30) NOT NULL DEFAULT 'tenant_admin',
    status                 VARCHAR(30) NOT NULL DEFAULT 'uploaded'
                           CHECK (status IN ('uploaded','previewing','previewed','processing','completed','completed_with_warnings','failed','needs_review')),
    total_rows             INT NOT NULL DEFAULT 0,
    successful_rows        INT NOT NULL DEFAULT 0,
    updated_rows           INT NOT NULL DEFAULT 0,
    skipped_rows           INT NOT NULL DEFAULT 0,
    failed_rows            INT NOT NULL DEFAULT 0,
    duplicate_rows         INT NOT NULL DEFAULT 0,
    unknown_referrer_rows  INT NOT NULL DEFAULT 0,
    unknown_partner_rows   INT NOT NULL DEFAULT 0,
    pricing_issue_rows     INT NOT NULL DEFAULT 0,
    blocked_rows           INT NOT NULL DEFAULT 0,
    summary_json           JSONB,
    started_at             TIMESTAMPTZ,
    completed_at           TIMESTAMPTZ,
    created_at             TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at             TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_import_batches_tenant  ON import_batches(tenant_id);
CREATE INDEX IF NOT EXISTS idx_import_batches_status  ON import_batches(status);
CREATE INDEX IF NOT EXISTS idx_import_batches_by      ON import_batches(imported_by_id);

-- ── LGU IDS Import Batch Rows ───────────────────────────────────
CREATE TABLE IF NOT EXISTS import_batch_rows (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    import_batch_id     UUID NOT NULL REFERENCES import_batches(id) ON DELETE CASCADE,
    row_number          INT NOT NULL,
    raw_data            JSONB NOT NULL DEFAULT '{}',
    normalized_data     JSONB NOT NULL DEFAULT '{}',
    computed_data       JSONB NOT NULL DEFAULT '{}',
    validation_status   VARCHAR(30) NOT NULL DEFAULT 'pending'
                        CHECK (validation_status IN ('pending','ready','needs_review','duplicate','unknown_referrer','unknown_partner','pricing_issue','unknown_lgu','failed','blocked','skipped')),
    issue_codes         JSONB DEFAULT '[]',
    row_action          VARCHAR(20) DEFAULT 'create'
                        CHECK (row_action IN ('create','update','skip','merge','overwrite','review','blocked')),
    existing_deal_id    TEXT,
    created_deal_id     TEXT,
    organization_id     TEXT,
    approved_by_id      TEXT,
    approved_at         TIMESTAMPTZ,
    error_message       TEXT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_ibr_batch  ON import_batch_rows(import_batch_id);
CREATE INDEX IF NOT EXISTS idx_ibr_status ON import_batch_rows(validation_status);
CREATE INDEX IF NOT EXISTS idx_ibr_action ON import_batch_rows(row_action);

-- ── Pending Referrer Invites from Import ────────────────────────
CREATE TABLE IF NOT EXISTS pending_referrer_invites (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    import_batch_id UUID REFERENCES import_batches(id) ON DELETE SET NULL,
    email           TEXT NOT NULL,
    name            TEXT,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending_invite'
                    CHECK (status IN ('pending_invite','invited','declined','expired')),
    row_ids         JSONB DEFAULT '[]',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (tenant_id, email)
);
CREATE INDEX IF NOT EXISTS idx_pri_tenant ON pending_referrer_invites(tenant_id);
CREATE INDEX IF NOT EXISTS idx_pri_status ON pending_referrer_invites(status);

ALTER TABLE import_batches           DISABLE ROW LEVEL SECURITY;
ALTER TABLE import_batch_rows        DISABLE ROW LEVEL SECURITY;
ALTER TABLE pending_referrer_invites DISABLE ROW LEVEL SECURITY;
