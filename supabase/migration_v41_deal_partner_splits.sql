-- Migration v41: Deal Partner Splits
-- Associates Partners to deals with split share allocations.
-- Partners can be pending/provisional (no accepted account yet).
-- Separate from commission_splits (which tracks referrer commission distribution).

CREATE TABLE IF NOT EXISTS deal_partner_splits (
    id                   UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id            TEXT        NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    deal_id              TEXT        NOT NULL,   -- references leads(id)
    partner_user_id      UUID        REFERENCES partner_users(id) ON DELETE SET NULL,
    partner_contact_id   UUID        REFERENCES contacts(id) ON DELETE SET NULL,
    partner_invitation_id TEXT,                  -- references contact_role_invitations(id)
    partner_name         TEXT        NOT NULL,
    partner_email        TEXT        NOT NULL,   -- normalized (lowercase, trimmed)
    split_share_value    NUMERIC(10,4) NOT NULL DEFAULT 0,
    split_share_type     TEXT        NOT NULL DEFAULT 'percentage'
                         CHECK (split_share_type IN ('percentage','fixed_amount')),
    currency             TEXT        DEFAULT 'PHP',
    status               TEXT        NOT NULL DEFAULT 'provisional'
                         CHECK (status IN ('active','pending_invite','provisional','invite_failed','removed')),
    source               TEXT        NOT NULL DEFAULT 'manual'
                         CHECK (source IN ('manual','import','admin_edit','referrer_added')),
    created_by_user_id   TEXT,
    updated_by_user_id   TEXT,
    accepted_at          TIMESTAMPTZ,
    removed_at           TIMESTAMPTZ,
    metadata             JSONB,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at           TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_dps_tenant_deal       ON deal_partner_splits(tenant_id, deal_id);
CREATE INDEX IF NOT EXISTS idx_dps_tenant_email      ON deal_partner_splits(tenant_id, partner_email);
CREATE INDEX IF NOT EXISTS idx_dps_tenant_deal_email ON deal_partner_splits(tenant_id, deal_id, partner_email);
CREATE INDEX IF NOT EXISTS idx_dps_partner_user      ON deal_partner_splits(partner_user_id) WHERE partner_user_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_dps_contact           ON deal_partner_splits(partner_contact_id) WHERE partner_contact_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_dps_status            ON deal_partner_splits(tenant_id, status) WHERE deleted_at IS NULL;

ALTER TABLE deal_partner_splits DISABLE ROW LEVEL SECURITY;
