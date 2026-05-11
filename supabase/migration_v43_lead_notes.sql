-- Migration v43: Lead Notes
-- Simple per-deal notes written by referrers or admins.
-- Table was previously created manually; this migration makes it idempotent.

CREATE TABLE IF NOT EXISTS lead_notes (
    id         UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    lead_id    UUID        NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
    text       TEXT        NOT NULL,
    author     VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_lead_notes_lead_id ON lead_notes (lead_id);
