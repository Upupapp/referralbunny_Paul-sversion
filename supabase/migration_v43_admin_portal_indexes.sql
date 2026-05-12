-- Migration v43: Admin Portal Performance Indexes
-- Adds indexes identified in the TEST/SWEEP/NOTIFY/ACTIONS audit for the admin portal.
-- All are CREATE INDEX IF NOT EXISTS so safe to run multiple times.

-- leads: status-based filtering on deals list and critical actions
CREATE INDEX IF NOT EXISTS idx_leads_tenant_status
    ON leads(tenant_id, status) WHERE deleted_at IS NULL;

-- leads: stage-based filtering (deal progress, stalled stage detection)
CREATE INDEX IF NOT EXISTS idx_leads_tenant_stage
    ON leads(tenant_id, stage) WHERE deleted_at IS NULL;

-- leads: commission_status (for critical actions, commission review queue)
CREATE INDEX IF NOT EXISTS idx_leads_tenant_commission_status
    ON leads(tenant_id, commission_status) WHERE deleted_at IS NULL;

-- leads: updated_at for stalled stage detection (deals not updated recently)
CREATE INDEX IF NOT EXISTS idx_leads_tenant_updated
    ON leads(tenant_id, updated_at DESC) WHERE deleted_at IS NULL;

-- partner_threads: reseller_unread for Critical Actions (admin view of partner messages)
CREATE INDEX IF NOT EXISTS idx_partner_threads_tenant_reseller_unread
    ON partner_threads(tenant_id, reseller_unread) WHERE reseller_unread > 0;

-- partner_threads: partner_id + partner_unread for partner Critical Actions
CREATE INDEX IF NOT EXISTS idx_partner_threads_partner_unread
    ON partner_threads(partner_id, partner_unread) WHERE partner_unread > 0;

-- message_threads: tenant_id + last_message_at (admin messages list ordering)
CREATE INDEX IF NOT EXISTS idx_message_threads_tenant_last_msg
    ON message_threads(tenant_id, last_message_at DESC);

-- message_threads: admin_unread (unread messages Critical Action)
CREATE INDEX IF NOT EXISTS idx_message_threads_tenant_admin_unread
    ON message_threads(tenant_id, admin_unread) WHERE admin_unread > 0;

-- tasks: tenant_id + status + due_at (overdue task critical action)
CREATE INDEX IF NOT EXISTS idx_tasks_tenant_status_due
    ON tasks(tenant_id, status, due_at) WHERE deleted_at IS NULL;

-- tasks: assigned_to_id (frequent filter on task detail + activity)
CREATE INDEX IF NOT EXISTS idx_tasks_assigned_to
    ON tasks(assigned_to_id, tenant_id) WHERE deleted_at IS NULL;

-- lead_history: action-based lookups (CriticalActionService recentReferrerAmountChanges)
CREATE INDEX IF NOT EXISTS idx_lead_history_tenant_action
    ON lead_history(tenant_id, action, created_at DESC);

-- commission_splits: lead_id + role (primary referrer lookup, split cap validation)
CREATE INDEX IF NOT EXISTS idx_commission_splits_lead_role
    ON commission_splits(lead_id, role);

-- deal_partner_splits: tenant + status (unread partner messages, admin view)
CREATE INDEX IF NOT EXISTS idx_deal_partner_splits_tenant_status
    ON deal_partner_splits(tenant_id, status) WHERE deleted_at IS NULL;
