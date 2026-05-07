-- Migration v40: Multi-role Referrer support
-- 1. linked_tenant_user_id — links a reseller record to a tenant_user (admin/manager who is also a referrer)
-- 2. invite_deal_ids — JSONB array of deal IDs summarized in the pending invitation
-- 3. invite_deal_count — cached count of deals in invitation summary
-- 4. invite_sent_at — timestamp of last automatic invite email (for 24h throttle)

ALTER TABLE resellers
  ADD COLUMN IF NOT EXISTS linked_tenant_user_id TEXT REFERENCES tenant_users(id) ON DELETE SET NULL,
  ADD COLUMN IF NOT EXISTS invite_deal_ids        JSONB        NOT NULL DEFAULT '[]'::jsonb,
  ADD COLUMN IF NOT EXISTS invite_deal_count      INTEGER      NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS invite_sent_at         TIMESTAMPTZ;

-- Index for linked admin/manager referrer lookups
CREATE INDEX IF NOT EXISTS idx_resellers_linked_tenant_user
  ON resellers(linked_tenant_user_id)
  WHERE linked_tenant_user_id IS NOT NULL;

-- Index for invite throttle queries (invite_sent_at IS NOT NULL AND status = 'invited')
CREATE INDEX IF NOT EXISTS idx_resellers_invite_sent_at
  ON resellers(tenant_id, invite_sent_at)
  WHERE status = 'invited' AND invite_sent_at IS NOT NULL;
