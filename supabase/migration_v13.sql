-- ============================================================
-- migration_v13.sql : Reseller Required Documents System
-- Run after migration_v12.sql
-- ============================================================

-- ── Document requirements (admin-configured per tenant) ───────────────────
CREATE TABLE IF NOT EXISTS reseller_required_documents (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id        UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    label            VARCHAR(150) NOT NULL,
    document_type    VARCHAR(100) NOT NULL DEFAULT 'custom',
    description      TEXT,
    accepted_formats VARCHAR(255) DEFAULT 'PDF, JPG, PNG',
    is_required      BOOLEAN NOT NULL DEFAULT true,
    is_active        BOOLEAN NOT NULL DEFAULT true,
    display_order    INTEGER NOT NULL DEFAULT 0,
    created_at       TIMESTAMPTZ DEFAULT NOW(),
    updated_at       TIMESTAMPTZ DEFAULT NOW()
);

-- ── Per-reseller document submissions ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS reseller_document_submissions (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id               UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    reseller_id             UUID NOT NULL REFERENCES resellers(id) ON DELETE CASCADE,
    required_document_id    UUID NOT NULL REFERENCES reseller_required_documents(id) ON DELETE CASCADE,
    file_url                TEXT,
    file_name               VARCHAR(255),
    status                  VARCHAR(50) NOT NULL DEFAULT 'not_submitted',
    submitted_at            TIMESTAMPTZ,
    reviewed_at             TIMESTAMPTZ,
    reviewed_by             VARCHAR(255),
    review_notes            TEXT,
    created_at              TIMESTAMPTZ DEFAULT NOW(),
    updated_at              TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(reseller_id, required_document_id)
);

CREATE INDEX IF NOT EXISTS idx_req_docs_tenant      ON reseller_required_documents(tenant_id, is_active);
CREATE INDEX IF NOT EXISTS idx_doc_subs_reseller    ON reseller_document_submissions(reseller_id);
CREATE INDEX IF NOT EXISTS idx_doc_subs_doc         ON reseller_document_submissions(required_document_id);
CREATE INDEX IF NOT EXISTS idx_doc_subs_tenant      ON reseller_document_submissions(tenant_id);

-- ── Default: LGU IDS requires a Valid Government ID ───────────────────────
-- Runs only if a tenant named "LGU IDS" exists
INSERT INTO reseller_required_documents
    (tenant_id, label, document_type, description, accepted_formats, is_required, display_order)
SELECT
    id,
    'Valid Government ID',
    'valid_id',
    'A clear photo or scan of any valid government-issued ID (SSS, PhilHealth, GSIS, Passport, Driver''s License, Voter''s ID, etc.)',
    'PDF, JPG, PNG',
    true,
    1
FROM tenants
WHERE name ILIKE '%LGU IDS%' OR slug ILIKE '%lgu%ids%' OR slug = 'lgu-ids'
LIMIT 1
ON CONFLICT DO NOTHING;
