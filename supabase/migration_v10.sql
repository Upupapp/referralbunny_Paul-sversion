-- migration_v10.sql
-- Adds financial breakdown fields to the leads table
-- Base Cost + Added Amount = Contract Value
-- Company Share = Added Amount × 30%
-- Commission Pool = Added Amount × 70%

ALTER TABLE leads ADD COLUMN IF NOT EXISTS base_cost    NUMERIC(15,2) NOT NULL DEFAULT 0;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS added_amount NUMERIC(15,2) NOT NULL DEFAULT 0;

-- For existing leads that have deal_value but no base_cost/added_amount,
-- migrate deal_value into added_amount so Commission Pool logic is preserved.
-- (Base Cost stays 0 for legacy records — they were not tracking cost separately.)
UPDATE leads
SET added_amount = deal_value
WHERE added_amount = 0 AND deal_value > 0;

COMMENT ON COLUMN leads.base_cost    IS 'Actual cost to deliver the deal (direct input, not derived)';
COMMENT ON COLUMN leads.added_amount IS 'Markup / margin added on top of base cost (direct input)';
-- leads.deal_value = base_cost + added_amount  (auto-updated by application layer)
