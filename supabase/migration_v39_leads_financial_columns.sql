-- Migration v39: Add financial columns to leads table
-- base_cost, added_amount, deal_value were in the Lead model fillable
-- but missing from the actual table schema.

ALTER TABLE leads
  ADD COLUMN IF NOT EXISTS base_cost    NUMERIC(15,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS added_amount NUMERIC(15,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS deal_value   NUMERIC(15,2) NOT NULL DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_leads_deal_value   ON leads (deal_value);
CREATE INDEX IF NOT EXISTS idx_leads_tenant_value ON leads (tenant_id, deal_value);
