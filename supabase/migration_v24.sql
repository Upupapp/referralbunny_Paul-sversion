-- ============================================================
-- migration_v24.sql : User profile fields + Partner user type
-- Run after migration_v23.sql
-- ============================================================

-- ── 1. Super Admin (users table) ──────────────────────────────
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS nickname           TEXT,
  ADD COLUMN IF NOT EXISTS phone_number       TEXT,
  ADD COLUMN IF NOT EXISTS job_title          TEXT,
  ADD COLUMN IF NOT EXISTS department         TEXT,
  ADD COLUMN IF NOT EXISTS location           TEXT,
  ADD COLUMN IF NOT EXISTS timezone           TEXT DEFAULT 'Asia/Manila',
  ADD COLUMN IF NOT EXISTS language           TEXT DEFAULT 'en',
  ADD COLUMN IF NOT EXISTS bio                TEXT,
  ADD COLUMN IF NOT EXISTS profile_photo_path TEXT;

-- ── 2. Tenant Users ───────────────────────────────────────────
ALTER TABLE tenant_users
  ADD COLUMN IF NOT EXISTS nickname           TEXT,
  ADD COLUMN IF NOT EXISTS phone_number       TEXT,
  ADD COLUMN IF NOT EXISTS job_title          TEXT,
  ADD COLUMN IF NOT EXISTS department         TEXT,
  ADD COLUMN IF NOT EXISTS organization       TEXT,
  ADD COLUMN IF NOT EXISTS location           TEXT,
  ADD COLUMN IF NOT EXISTS timezone           TEXT DEFAULT 'Asia/Manila',
  ADD COLUMN IF NOT EXISTS language           TEXT DEFAULT 'en',
  ADD COLUMN IF NOT EXISTS bio                TEXT,
  ADD COLUMN IF NOT EXISTS profile_photo_path TEXT;

-- ── 3. Resellers (Referrers) ──────────────────────────────────
ALTER TABLE resellers
  ADD COLUMN IF NOT EXISTS nickname           TEXT,
  ADD COLUMN IF NOT EXISTS job_title          TEXT,
  ADD COLUMN IF NOT EXISTS department         TEXT,
  ADD COLUMN IF NOT EXISTS organization       TEXT,
  ADD COLUMN IF NOT EXISTS location           TEXT,
  ADD COLUMN IF NOT EXISTS timezone           TEXT DEFAULT 'Asia/Manila',
  ADD COLUMN IF NOT EXISTS language           TEXT DEFAULT 'en',
  ADD COLUMN IF NOT EXISTS bio                TEXT,
  ADD COLUMN IF NOT EXISTS profile_photo_path TEXT,
  ADD COLUMN IF NOT EXISTS updated_at         TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_resellers_updated ON resellers(updated_at);

-- ── 4. Partner Users (new limited user type) ──────────────────
CREATE TABLE IF NOT EXISTS partner_users (
    id                  UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           TEXT        NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    email               TEXT        NOT NULL,
    password            TEXT,
    setup_token         TEXT,
    remember_token      TEXT,
    status              TEXT        NOT NULL DEFAULT 'invited'
                        CHECK (status IN ('invited','active','suspended','removed')),
    first_name          TEXT,
    last_name           TEXT,
    nickname            TEXT,
    phone_number        TEXT,
    organization        TEXT,
    location            TEXT,
    timezone            TEXT        DEFAULT 'Asia/Manila',
    language            TEXT        DEFAULT 'en',
    bio                 TEXT,
    profile_photo_path  TEXT,
    invited_by_type     TEXT,
    invited_by_id       TEXT,
    setup_completed_at  TIMESTAMPTZ,
    last_login_at       TIMESTAMPTZ,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(tenant_id, email)
);

CREATE INDEX IF NOT EXISTS idx_partner_users_tenant   ON partner_users(tenant_id);
CREATE INDEX IF NOT EXISTS idx_partner_users_email    ON partner_users(email);
CREATE INDEX IF NOT EXISTS idx_partner_users_status   ON partner_users(status);
CREATE INDEX IF NOT EXISTS idx_partner_users_token    ON partner_users(setup_token);

-- ── 5. Deal Partners (Partner-Deal association) ───────────────
CREATE TABLE IF NOT EXISTS deal_partners (
    id                UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id         TEXT        NOT NULL,
    deal_id           TEXT        NOT NULL,
    partner_user_id   UUID        NOT NULL REFERENCES partner_users(id) ON DELETE CASCADE,
    added_by_id       TEXT,
    added_by_type     TEXT,
    status            TEXT        NOT NULL DEFAULT 'invited'
                      CHECK (status IN ('invited','active','removed','declined')),
    permissions       JSONB       NOT NULL DEFAULT '{"can_view_deal":true,"can_message_referrer":true,"can_upload_files":false,"can_comment":false,"can_edit_deal":false,"can_change_stage":false,"can_view_commissions":false}'::jsonb,
    invited_at        TIMESTAMPTZ DEFAULT NOW(),
    accepted_at       TIMESTAMPTZ,
    removed_at        TIMESTAMPTZ,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_deal_partners_tenant    ON deal_partners(tenant_id);
CREATE INDEX IF NOT EXISTS idx_deal_partners_deal      ON deal_partners(deal_id);
CREATE INDEX IF NOT EXISTS idx_deal_partners_partner   ON deal_partners(partner_user_id);
CREATE INDEX IF NOT EXISTS idx_deal_partners_status    ON deal_partners(status);

ALTER TABLE partner_users  DISABLE ROW LEVEL SECURITY;
ALTER TABLE deal_partners   DISABLE ROW LEVEL SECURITY;
