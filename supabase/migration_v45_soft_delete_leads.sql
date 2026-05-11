-- Migration v45: Soft-delete (archive) for admin-deleted leads
-- Admin "delete" now soft-deletes for a 10-day recovery window.
-- Permanent purge runs via: php artisan leads:purge-archived (daily at 01:30)

ALTER TABLE leads
    ADD COLUMN IF NOT EXISTS deleted_at  TIMESTAMPTZ DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS deleted_by  VARCHAR(255) DEFAULT NULL;

-- Partial index: archive-tab queries are fast even on large tenants
CREATE INDEX IF NOT EXISTS idx_leads_soft_deleted
    ON leads (tenant_id, deleted_at)
    WHERE deleted_at IS NOT NULL;
