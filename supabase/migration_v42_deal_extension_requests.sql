-- Migration v42: Deal Assignment Extension Requests
-- Allows LGU IDS Referrers (and permitted Partners/Managers) to request
-- an extension of their deal assignment/stage expiry. Admins can approve/reject.

CREATE TABLE IF NOT EXISTS deal_assignment_extension_requests (
    id                       UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id                TEXT        NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    deal_id                  TEXT        NOT NULL,   -- references leads(id)
    requested_by_user_id     TEXT        NOT NULL,
    requested_by_role        TEXT        NOT NULL,   -- referrer, manager, admin
    current_stage            TEXT        NOT NULL,
    current_expiry_at        TIMESTAMPTZ,            -- computed from days_left at request time
    current_days_left        INTEGER,
    requested_days           INTEGER     NOT NULL,
    requested_new_expiry_at  TIMESTAMPTZ,
    approved_days            INTEGER,
    approved_new_expiry_at   TIMESTAMPTZ,
    reason                   TEXT        NOT NULL,
    admin_note               TEXT,
    status                   TEXT        NOT NULL DEFAULT 'pending_review'
                             CHECK (status IN ('pending_review','approved','rejected','clarification_requested','cancelled','expired')),
    reviewed_by_user_id      TEXT,
    reviewed_at              TIMESTAMPTZ,
    metadata                 JSONB,
    created_at               TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at               TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_daer_tenant_deal   ON deal_assignment_extension_requests(tenant_id, deal_id);
CREATE INDEX IF NOT EXISTS idx_daer_status        ON deal_assignment_extension_requests(tenant_id, status);
CREATE INDEX IF NOT EXISTS idx_daer_requester     ON deal_assignment_extension_requests(requested_by_user_id);
CREATE INDEX IF NOT EXISTS idx_daer_pending       ON deal_assignment_extension_requests(tenant_id, deal_id)
    WHERE status = 'pending_review';

ALTER TABLE deal_assignment_extension_requests DISABLE ROW LEVEL SECURITY;
