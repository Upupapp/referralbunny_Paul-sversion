-- Migration v38: Deal Comments
-- Tenant-scoped comments on deal/lead records.
-- Supports shared comments and internal admin-only notes.

CREATE TABLE IF NOT EXISTS deal_comments (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       VARCHAR     NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    deal_id         UUID        NOT NULL REFERENCES leads(id) ON DELETE CASCADE,

    author_user_id  VARCHAR(255) NOT NULL,
    author_role     VARCHAR(50)  NOT NULL,   -- tenant_admin|tenant_manager|referrer|partner|super_admin

    body            TEXT         NOT NULL,
    visibility      VARCHAR(30)  NOT NULL DEFAULT 'shared'
        CHECK (visibility IN ('shared', 'internal_admin')),

    parent_comment_id UUID       REFERENCES deal_comments(id) ON DELETE SET NULL,

    edited_at       TIMESTAMPTZ,
    deleted_at      TIMESTAMPTZ,             -- soft delete

    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_deal_comments_deal      ON deal_comments (deal_id);
CREATE INDEX IF NOT EXISTS idx_deal_comments_tenant    ON deal_comments (tenant_id);
CREATE INDEX IF NOT EXISTS idx_deal_comments_author    ON deal_comments (author_user_id);
CREATE INDEX IF NOT EXISTS idx_deal_comments_visibility ON deal_comments (deal_id, visibility);
