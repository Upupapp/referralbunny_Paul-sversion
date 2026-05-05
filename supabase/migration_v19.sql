-- ============================================================
-- migration_v19.sql : remember_token for tenant_users
-- Run after migration_v18.sql
-- ============================================================

ALTER TABLE tenant_users
  ADD COLUMN IF NOT EXISTS remember_token VARCHAR(100);

CREATE INDEX IF NOT EXISTS idx_tenant_users_email ON tenant_users(email);
