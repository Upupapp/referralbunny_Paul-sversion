-- migration_v32.sql
-- First sign-in onboarding walkthrough and gamified R Bunny helper state tracking
-- Run after migration_v31.sql

CREATE TABLE IF NOT EXISTS user_onboarding_states (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_type               TEXT NOT NULL,   -- super_admin | tenant_user | reseller | partner
    user_id                 TEXT NOT NULL,
    tenant_id               TEXT,            -- NULL for super_admin
    role_key                TEXT NOT NULL,   -- owner | admin | manager | member | referrer | partner | super_admin
    walkthrough_status      TEXT NOT NULL DEFAULT 'not_started', -- not_started | in_progress | completed | skipped
    walkthrough_step        INTEGER  NOT NULL DEFAULT 0,
    completed_tasks         JSONB    NOT NULL DEFAULT '[]',       -- array of task_key strings
    dismissed_prompts       JSONB    NOT NULL DEFAULT '[]',       -- task_keys user dismissed in bot
    snoozed_until           TIMESTAMPTZ,
    is_fully_ready          BOOLEAN  NOT NULL DEFAULT FALSE,
    first_seen_at           TIMESTAMPTZ,
    walkthrough_completed_at TIMESTAMPTZ,
    fully_ready_at          TIMESTAMPTZ,
    last_prompted_at        TIMESTAMPTZ,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Unique: one onboarding state per user per tenant (null tenant_id for super admin)
CREATE UNIQUE INDEX IF NOT EXISTS idx_onboarding_states_unique
    ON user_onboarding_states (user_type, user_id, COALESCE(tenant_id, '__global__'));

CREATE INDEX IF NOT EXISTS idx_onboarding_states_user
    ON user_onboarding_states (user_type, user_id);

CREATE INDEX IF NOT EXISTS idx_onboarding_states_tenant
    ON user_onboarding_states (tenant_id)
    WHERE tenant_id IS NOT NULL;
