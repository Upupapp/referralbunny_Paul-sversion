-- Migration v44: Archive fields on leads + compound index on deal_approval_requests
-- Fixes: archived deals were indistinguishable from naturally-expired deals (both used status='expired')
-- Adds: archived_at, archive_reason columns to leads; compound index for approval queries

-- Leads: dedicated archive columns
ALTER TABLE leads
    ADD COLUMN IF NOT EXISTS archived_at    TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS archive_reason TEXT;

-- Partial index so archived-deal queries are fast
CREATE INDEX IF NOT EXISTS idx_leads_archived_at
    ON leads (tenant_id, archived_at)
    WHERE archived_at IS NOT NULL;

-- deal_approval_requests: compound index for the most common lookup pattern
-- (find pending archive requests for a tenant)
CREATE INDEX IF NOT EXISTS idx_deal_approval_requests_tenant_type_status
    ON deal_approval_requests (tenant_id, type, status);
