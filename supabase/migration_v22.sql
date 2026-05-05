-- ============================================================
-- migration_v22.sql : Notification system expansion
-- Extends notifications table + adds preference + dedup tables
-- Run after migration_v21.sql
-- ============================================================

-- ── 1. Extend notifications table ─────────────────────────────
ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS notifiable_type  TEXT,
  ADD COLUMN IF NOT EXISTS notifiable_id    TEXT,
  ADD COLUMN IF NOT EXISTS title            TEXT,
  ADD COLUMN IF NOT EXISTS action_label     TEXT,
  ADD COLUMN IF NOT EXISTS deduplication_key TEXT,
  ADD COLUMN IF NOT EXISTS archived_at      TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS expires_at       TIMESTAMPTZ;

-- ── 2. Expand category constraint ─────────────────────────────
ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_category_check;
ALTER TABLE notifications ADD CONSTRAINT notifications_category_check CHECK (category IN (
  'billing','tenant_health','analytics','messaging','system','growth','approvals',
  'deal_pipeline','organization','reseller_referrer','commission',
  'import_export','report','task_approval','message_comment',
  'system_job','dashboard_briefing','account_profile','lgu_ids_protected_rule',
  'auth_security','tenant_workspace','billing_subscription'
));

-- ── 3. Expand priority constraint (keep medium/critical for compat) ──
ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_priority_check;
ALTER TABLE notifications ADD CONSTRAINT notifications_priority_check CHECK (
  priority IN ('low','normal','medium','high','critical','urgent')
);

-- ── 4. Indexes for new columns ─────────────────────────────────
CREATE UNIQUE INDEX IF NOT EXISTS notifications_dedup_key_idx
  ON notifications(deduplication_key)
  WHERE deduplication_key IS NOT NULL;

CREATE INDEX IF NOT EXISTS notifications_notifiable_idx
  ON notifications(notifiable_type, notifiable_id);

CREATE INDEX IF NOT EXISTS notifications_archived_at_idx
  ON notifications(archived_at);

-- ── 5. Per-user notification preferences ──────────────────────
CREATE TABLE IF NOT EXISTS notification_preferences (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    notifiable_type TEXT        NOT NULL CHECK (notifiable_type IN ('super_admin','tenant_admin','reseller')),
    notifiable_id   TEXT        NOT NULL,
    tenant_id       TEXT,
    category        TEXT        NOT NULL,
    in_app_enabled  BOOLEAN     NOT NULL DEFAULT TRUE,
    email_enabled   BOOLEAN     NOT NULL DEFAULT TRUE,
    frequency       TEXT        NOT NULL DEFAULT 'immediate'
                    CHECK (frequency IN ('immediate','daily_digest','weekly_digest','muted')),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(notifiable_type, notifiable_id, category)
);

CREATE INDEX IF NOT EXISTS notification_prefs_notifiable_idx
  ON notification_preferences(notifiable_type, notifiable_id);

-- ── 6. Disable RLS (consistent with other tables) ─────────────
ALTER TABLE notification_preferences DISABLE ROW LEVEL SECURITY;
