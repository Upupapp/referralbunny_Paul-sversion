-- ============================================================
-- Migration v36: Contact Role Invitations
-- Tracks role assignment invitations from a Contact record
-- to Referrer / Tenant Manager / Tenant Staff / Partner roles.
-- ============================================================

CREATE TABLE IF NOT EXISTS contact_role_invitations (
    id                   UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id            VARCHAR     NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    contact_id           UUID        NOT NULL REFERENCES contacts(id) ON DELETE CASCADE,

    -- Who is being invited and to what
    invited_email        VARCHAR(255) NOT NULL,
    invited_role         VARCHAR(50)  NOT NULL
        CHECK (invited_role IN ('referrer','tenant_manager','tenant_staff','partner')),

    -- Who sent the invite
    invited_by_user_id   VARCHAR(255),
    invited_by_role      VARCHAR(50),   -- owner|manager|admin|referrer

    -- Status tracking
    status               VARCHAR(30)  NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','accepted','expired','revoked')),
    token                VARCHAR(80)  NOT NULL UNIQUE,
    expires_at           TIMESTAMPTZ,
    accepted_at          TIMESTAMPTZ,
    revoked_at           TIMESTAMPTZ,
    revoked_by_user_id   VARCHAR(255),

    -- For Partner role: which deal
    associated_deal_id   UUID         REFERENCES leads(id) ON DELETE SET NULL,

    -- For Tenant Manager role: optional permission preset
    permissions_json     JSONB,

    -- Optional personal note in the invite email
    message              TEXT,

    -- Reminder tracking
    reminder_count       INTEGER      NOT NULL DEFAULT 0,
    last_reminder_sent_at TIMESTAMPTZ,

    -- Links to the created user records (set after acceptance)
    linked_reseller_id      VARCHAR(255),
    linked_tenant_user_id   VARCHAR(255),
    linked_partner_id       VARCHAR(255),

    created_at           TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_cri_tenant       ON contact_role_invitations (tenant_id);
CREATE INDEX IF NOT EXISTS idx_cri_contact      ON contact_role_invitations (contact_id);
CREATE INDEX IF NOT EXISTS idx_cri_email        ON contact_role_invitations (invited_email);
CREATE INDEX IF NOT EXISTS idx_cri_token        ON contact_role_invitations (token);
CREATE INDEX IF NOT EXISTS idx_cri_status       ON contact_role_invitations (status);
CREATE INDEX IF NOT EXISTS idx_cri_deal         ON contact_role_invitations (associated_deal_id);
CREATE INDEX IF NOT EXISTS idx_cri_role_status  ON contact_role_invitations (tenant_id, invited_role, status);
