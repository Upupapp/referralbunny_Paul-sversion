-- migration_v31.sql
-- Invitation reminder tracking: adds columns to tenant_invitations
-- Run after migration_v30.sql

ALTER TABLE tenant_invitations
  ADD COLUMN IF NOT EXISTS initial_email_sent_at      TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS reminder_count              INTEGER      NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS last_reminder_sent_at       TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS next_reminder_at            TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS inviter_reminder_count      INTEGER      NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS last_inviter_reminder_at    TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS next_inviter_reminder_at    TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS reminder_suppressed_at      TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS reminder_suppressed_reason  TEXT,
  ADD COLUMN IF NOT EXISTS last_manual_resend_at       TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS permissions_preset          TEXT;

-- Indexes for the scheduled reminder query (hourly sweep)
CREATE INDEX IF NOT EXISTS idx_tenant_invitations_next_reminder
  ON tenant_invitations (next_reminder_at)
  WHERE status = 'pending';

CREATE INDEX IF NOT EXISTS idx_tenant_invitations_next_inviter_reminder
  ON tenant_invitations (next_inviter_reminder_at)
  WHERE status = 'pending';
