-- migration_v33.sql
-- R Bunny on-site assistant: suggestion tracking and user preferences
-- Run after migration_v32.sql

-- Stores dismissed/snoozed suggestion state so R Bunny remembers what to skip
CREATE TABLE IF NOT EXISTS r_bunny_suggestions (
    id                  UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    user_type           TEXT        NOT NULL,   -- super_admin | tenant_user | reseller | partner
    user_id             TEXT        NOT NULL,
    tenant_id           TEXT,                   -- NULL for super_admin
    suggestion_key      TEXT        NOT NULL,
    status              TEXT        NOT NULL DEFAULT 'pending',  -- pending | shown | dismissed | snoozed
    snoozed_until       TIMESTAMPTZ,
    related_type        TEXT,                   -- deal | invite | message | import | profile | onboarding
    related_id          TEXT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_r_bunny_suggestions_unique
    ON r_bunny_suggestions (user_type, user_id, COALESCE(tenant_id,'__global__'), suggestion_key);

CREATE INDEX IF NOT EXISTS idx_r_bunny_suggestions_user
    ON r_bunny_suggestions (user_type, user_id);

-- Stores per-user preferences for R Bunny behavior
CREATE TABLE IF NOT EXISTS r_bunny_preferences (
    id                      UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    user_type               TEXT        NOT NULL,
    user_id                 TEXT        NOT NULL,
    tenant_id               TEXT,
    show_proactive_tips     BOOLEAN     NOT NULL DEFAULT TRUE,
    show_onboarding_tips    BOOLEAN     NOT NULL DEFAULT TRUE,
    show_task_reminders     BOOLEAN     NOT NULL DEFAULT TRUE,
    show_message_reminders  BOOLEAN     NOT NULL DEFAULT TRUE,
    collapsed_by_default    BOOLEAN     NOT NULL DEFAULT FALSE,
    last_opened_at          TIMESTAMPTZ,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_r_bunny_prefs_unique
    ON r_bunny_preferences (user_type, user_id, COALESCE(tenant_id,'__global__'));
