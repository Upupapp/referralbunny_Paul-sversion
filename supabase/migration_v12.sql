-- ============================================================
-- migration_v12.sql : Contacts, Organizations, Deal-Contact links
-- Run after migration_v11.sql
-- ============================================================

-- ── Organizations ────────────────────���────────────────────────��──────────
CREATE TABLE IF NOT EXISTS organizations (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id    UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name         VARCHAR(255) NOT NULL,
    industry     VARCHAR(150),
    website      VARCHAR(500),
    address      TEXT,
    city         VARCHAR(100),
    country      VARCHAR(100) DEFAULT 'Philippines',
    notes        TEXT,
    data         JSONB DEFAULT '{}',
    created_at   TIMESTAMPTZ DEFAULT NOW(),
    updated_at   TIMESTAMPTZ DEFAULT NOW()
);

-- ── Contacts (people) ──────────────────────���─────────────────────────────
CREATE TABLE IF NOT EXISTS contacts (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    organization_id UUID REFERENCES organizations(id) ON DELETE SET NULL,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100),
    email           VARCHAR(255),
    phone           VARCHAR(50),
    job_title       VARCHAR(150),
    status          VARCHAR(50) NOT NULL DEFAULT 'active',  -- active | inactive | prospect
    notes           TEXT,
    data            JSONB DEFAULT '{}',
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ── Deal ↔ Contact many-to-many ───────────────────────────��──────────────
CREATE TABLE IF NOT EXISTS deal_contacts (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    deal_id     UUID NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
    contact_id  UUID NOT NULL REFERENCES contacts(id) ON DELETE CASCADE,
    role        VARCHAR(100),   -- e.g. "Decision Maker", "Champion", "Technical Lead"
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(deal_id, contact_id)
);

CREATE INDEX IF NOT EXISTS idx_contacts_tenant       ON contacts(tenant_id);
CREATE INDEX IF NOT EXISTS idx_contacts_org          ON contacts(organization_id);
CREATE INDEX IF NOT EXISTS idx_organizations_tenant  ON organizations(tenant_id);
CREATE INDEX IF NOT EXISTS idx_deal_contacts_deal    ON deal_contacts(deal_id);
CREATE INDEX IF NOT EXISTS idx_deal_contacts_contact ON deal_contacts(contact_id);
