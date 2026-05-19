-- ─────────────────────────────────────────────────────────────────────────
-- OPTIMIZE: Critical Actions Counter — targeted composite indexes
-- Covers the four hottest CriticalActionService source queries that run on
-- every badge cache-miss.  All indexes are partial to stay as small as possible.
-- ─────────────────────────────────────────────────────────────────────────

-- 1. pendingArchiveRequests + pendingStageMoveRequests
--    WHERE tenant_id=? AND type IN ('deal_archive','deal_stage_move') AND status='pending'
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_dar_tenant_type_pending
    ON deal_approval_requests (tenant_id, type)
    WHERE status = 'pending';

-- 2. pendingExtensionRequests
--    WHERE tenant_id=? AND status IN ('pending_review','clarification_requested')
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_daer_tenant_pending_statuses
    ON deal_assignment_extension_requests (tenant_id, status)
    WHERE status IN ('pending_review', 'clarification_requested');

-- 3. commissionReviewQueue
--    WHERE tenant_id=? AND stage IN ('signed','paid') AND commission_status='pending' AND deleted_at IS NULL
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_commission_review_queue
    ON leads (tenant_id, stage, updated_at)
    WHERE commission_status = 'pending'
      AND stage IN ('signed', 'paid')
      AND deleted_at IS NULL;

-- 4. stalledDeals
--    WHERE tenant_id=? AND status IN ('active','expiring') AND updated_at < threshold AND deleted_at IS NULL
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_stalled_deals
    ON leads (tenant_id, updated_at)
    WHERE status IN ('active', 'expiring')
      AND deleted_at IS NULL
      AND stage NOT IN ('paid', 'archived');
