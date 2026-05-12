-- Migration v46: Deal Note Attachments & Mentions
-- File attachments on deal comments, and @mention records.

-- deal_note_attachments
CREATE TABLE IF NOT EXISTS deal_note_attachments (
    id                  UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           VARCHAR      NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    deal_comment_id     UUID         NOT NULL REFERENCES deal_comments(id) ON DELETE CASCADE,
    uploaded_by_id      VARCHAR(255) NOT NULL,
    uploaded_by_role    VARCHAR(50)  NOT NULL DEFAULT 'tenant_admin',
    disk                VARCHAR(50)  NOT NULL DEFAULT 'local',
    path                TEXT         NOT NULL,
    original_filename   VARCHAR(255) NOT NULL,
    stored_filename     VARCHAR(255) NOT NULL,
    mime_type           VARCHAR(255) NOT NULL,
    file_size           INTEGER      NOT NULL,
    file_type_group     VARCHAR(50)  NOT NULL DEFAULT 'document',
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_deal_note_attachments_comment ON deal_note_attachments (deal_comment_id);
CREATE INDEX IF NOT EXISTS idx_deal_note_attachments_tenant  ON deal_note_attachments (tenant_id);

-- deal_note_mentions
CREATE TABLE IF NOT EXISTS deal_note_mentions (
    id                      UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id               VARCHAR      NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    deal_comment_id         UUID         NOT NULL REFERENCES deal_comments(id) ON DELETE CASCADE,
    mentionable_type        VARCHAR(50)  NOT NULL,
    mentionable_id          VARCHAR(255) NOT NULL,
    display_name_snapshot   VARCHAR(255) NOT NULL DEFAULT '',
    created_at              TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_deal_note_mentions_comment ON deal_note_mentions (deal_comment_id);
CREATE INDEX IF NOT EXISTS idx_deal_note_mentions_tenant  ON deal_note_mentions (tenant_id);

-- client_request_id for idempotent note creation (if not already added)
ALTER TABLE deal_comments
    ADD COLUMN IF NOT EXISTS client_request_id VARCHAR(64);
CREATE INDEX IF NOT EXISTS idx_deal_comments_client_req ON deal_comments (tenant_id, deal_id, author_user_id, client_request_id)
    WHERE client_request_id IS NOT NULL;
