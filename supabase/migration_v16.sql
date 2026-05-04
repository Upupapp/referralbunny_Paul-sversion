-- ============================================================
-- migration_v16.sql : Reseller Authentication
-- Run after migration_v15.sql
-- ============================================================

ALTER TABLE resellers
  ADD COLUMN IF NOT EXISTS password       VARCHAR(255),
  ADD COLUMN IF NOT EXISTS setup_token    VARCHAR(100),
  ADD COLUMN IF NOT EXISTS remember_token VARCHAR(100);

CREATE INDEX IF NOT EXISTS idx_resellers_email       ON resellers(email);
CREATE INDEX IF NOT EXISTS idx_resellers_setup_token ON resellers(setup_token);
