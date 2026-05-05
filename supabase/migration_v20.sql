-- ============================================================
-- migration_v20.sql : Tenant subdomains
-- Run after migration_v19.sql
-- ============================================================

ALTER TABLE tenants
  ADD COLUMN IF NOT EXISTS subdomain VARCHAR(100) UNIQUE;

CREATE INDEX IF NOT EXISTS idx_tenants_subdomain ON tenants(subdomain);

-- Seed LGU IDS subdomain
UPDATE tenants SET subdomain = 'lguids' WHERE id = 'lgu-ids';
