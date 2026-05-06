-- migration_v28.sql
-- Contacts Import: extend contacts table, import_batches, import_batch_rows, pending_partner_invites

-- ── Extend contacts table with ownership and import fields ─────
ALTER TABLE contacts
  ALTER COLUMN first_name DROP NOT NULL;

ALTER TABLE contacts
  ADD COLUMN IF NOT EXISTS owner_user_id       TEXT REFERENCES tenant_users(id) ON DELETE SET NULL,
  ADD COLUMN IF NOT EXISTS owner_role          VARCHAR(30),
  ADD COLUMN IF NOT EXISTS owner_referrer_id   TEXT REFERENCES resellers(id) ON DELETE SET NULL,
  ADD COLUMN IF NOT EXISTS linked_user_id      TEXT,
  ADD COLUMN IF NOT EXISTS full_name           VARCHAR(200),
  ADD COLUMN IF NOT EXISTS nickname            VARCHAR(50),
  ADD COLUMN IF NOT EXISTS alternate_email     VARCHAR(255),
  ADD COLUMN IF NOT EXISTS alternate_phone     VARCHAR(50),
  ADD COLUMN IF NOT EXISTS department          VARCHAR(100),
  ADD COLUMN IF NOT EXISTS company_or_organization VARCHAR(150),
  ADD COLUMN IF NOT EXISTS address             TEXT,
  ADD COLUMN IF NOT EXISTS city_or_municipality VARCHAR(100),
  ADD COLUMN IF NOT EXISTS province            VARCHAR(100),
  ADD COLUMN IF NOT EXISTS region              VARCHAR(100),
  ADD COLUMN IF NOT EXISTS country             VARCHAR(100),
  ADD COLUMN IF NOT EXISTS timezone            VARCHAR(100),
  ADD COLUMN IF NOT EXISTS language            VARCHAR(20),
  ADD COLUMN IF NOT EXISTS tags                JSONB DEFAULT '[]',
  ADD COLUMN IF NOT EXISTS contact_type        VARCHAR(50) DEFAULT 'general_contact',
  ADD COLUMN IF NOT EXISTS intended_role       VARCHAR(50),
  ADD COLUMN IF NOT EXISTS visibility_scope    VARCHAR(30) DEFAULT 'tenant',
  ADD COLUMN IF NOT EXISTS source              VARCHAR(100),
  ADD COLUMN IF NOT EXISTS internal_reference_id VARCHAR(100),
  ADD COLUMN IF NOT EXISTS consent_status      VARCHAR(30),
  ADD COLUMN IF NOT EXISTS consent_source      VARCHAR(100),
  ADD COLUMN IF NOT EXISTS consent_date        DATE,
  ADD COLUMN IF NOT EXISTS communication_preference VARCHAR(50),
  ADD COLUMN IF NOT EXISTS do_not_contact      BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS imported_from_batch_id UUID REFERENCES import_batches(id) ON DELETE SET NULL,
  ADD COLUMN IF NOT EXISTS created_by_user_id  TEXT,
  ADD COLUMN IF NOT EXISTS updated_by_user_id  TEXT,
  ADD COLUMN IF NOT EXISTS archived_at         TIMESTAMPTZ;

-- Indexes for ownership and import queries
CREATE INDEX IF NOT EXISTS idx_contacts_owner_referrer ON contacts(owner_referrer_id);
CREATE INDEX IF NOT EXISTS idx_contacts_owner_user     ON contacts(owner_user_id);
CREATE INDEX IF NOT EXISTS idx_contacts_email          ON contacts(email);
CREATE INDEX IF NOT EXISTS idx_contacts_type           ON contacts(contact_type);
CREATE INDEX IF NOT EXISTS idx_contacts_batch          ON contacts(imported_from_batch_id);
CREATE INDEX IF NOT EXISTS idx_contacts_visibility     ON contacts(visibility_scope);

-- ── Extend import_batches for contacts-specific counters ───────
ALTER TABLE import_batches
  ADD COLUMN IF NOT EXISTS possible_duplicate_rows          INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS same_email_different_referrer_rows INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS unknown_deal_rows                INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS unknown_organization_rows        INT NOT NULL DEFAULT 0;

-- ── Extend import_batch_rows for contact tracking ─────────────
ALTER TABLE import_batch_rows
  ADD COLUMN IF NOT EXISTS existing_contact_id TEXT,
  ADD COLUMN IF NOT EXISTS created_contact_id  TEXT;

-- NOTE: validation_status uses CHECK constraint from v26.
-- For contacts import, we use extended status values.
-- Drop old constraint and recreate with contact statuses:
ALTER TABLE import_batch_rows
  DROP CONSTRAINT IF EXISTS import_batch_rows_validation_status_check;

ALTER TABLE import_batch_rows
  ADD CONSTRAINT import_batch_rows_validation_status_check
  CHECK (validation_status IN (
    'pending','ready','needs_review',
    'duplicate','possible_duplicate',
    'same_email_other_referrer',
    'unknown_referrer','unknown_partner',
    'unknown_org','unknown_deal',
    'existing_user',
    'pricing_issue','unknown_lgu',
    'do_not_contact',
    'failed','blocked','skipped'
  ));

-- ── Pending partner invite staging table ──────────────────────
CREATE TABLE IF NOT EXISTS pending_partner_invites (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    import_batch_id UUID REFERENCES import_batches(id) ON DELETE SET NULL,
    contact_id      TEXT,
    email           TEXT NOT NULL,
    name            TEXT,
    deal_id         TEXT,
    added_by_referrer_id TEXT,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending_invite'
                    CHECK (status IN ('pending_invite','invited','declined','expired')),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_ppi_tenant ON pending_partner_invites(tenant_id);
CREATE INDEX IF NOT EXISTS idx_ppi_status ON pending_partner_invites(status);
ALTER TABLE pending_partner_invites DISABLE ROW LEVEL SECURITY;
