-- ============================================================
-- migration_v17.sql : LGU IDS Deal Claiming + Pipeline Stage Rules
-- Run after migration_v16.sql
-- ============================================================

-- Link leads to organizations (used for LGU IDS one-deal-per-org rule)
ALTER TABLE leads
  ADD COLUMN IF NOT EXISTS organization_id UUID REFERENCES organizations(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_leads_organization_id ON leads(organization_id);

-- ── Tenant pipeline stage rules ───────────────────────────────
-- Stores per-stage time limits. LGU IDS values are LOCKED (see protection rule).
CREATE TABLE IF NOT EXISTS tenant_pipeline_stage_rules (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    stage       VARCHAR(100) NOT NULL,
    max_days    INTEGER NOT NULL DEFAULT 30,
    warning_days INTEGER NOT NULL DEFAULT 5,
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(tenant_id, stage)
);

CREATE INDEX IF NOT EXISTS idx_pipeline_rules_tenant ON tenant_pipeline_stage_rules(tenant_id);

-- ── Seed LGU IDS pipeline stage rules (LOCKED — do not change) ─
-- These values represent the agreed deal pipeline time limits for LGU IDS.
INSERT INTO tenant_pipeline_stage_rules (tenant_id, stage, max_days, warning_days) VALUES
    ('lgu-ids', 'introduction',   14, 3),
    ('lgu-ids', 'presentation',   21, 5),
    ('lgu-ids', 'contract_sent',  30, 7),
    ('lgu-ids', 'signed',         30, 7)
ON CONFLICT (tenant_id, stage) DO NOTHING;
