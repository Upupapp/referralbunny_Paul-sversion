-- ── Add 'manager' role to tenant role constraints ─────────────
-- Drop and recreate role CHECK constraints to include 'manager'

ALTER TABLE tenant_memberships
  DROP CONSTRAINT IF EXISTS tenant_memberships_role_check;

ALTER TABLE tenant_memberships
  ADD CONSTRAINT tenant_memberships_role_check
  CHECK (role IN ('owner','admin','manager','member','viewer'));

ALTER TABLE tenant_invitations
  DROP CONSTRAINT IF EXISTS tenant_invitations_role_check;

ALTER TABLE tenant_invitations
  ADD CONSTRAINT tenant_invitations_role_check
  CHECK (role IN ('owner','admin','manager','member','viewer'));

-- ── Add permission fields to tenant_memberships ───────────────
ALTER TABLE tenant_memberships
  ADD COLUMN IF NOT EXISTS permissions_json           JSONB,
  ADD COLUMN IF NOT EXISTS can_manage_billing         BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS can_delete_tenant          BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS can_transfer_ownership     BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS is_custom_permissions      BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS invited_by_user_id         UUID REFERENCES tenant_users(id) ON DELETE SET NULL;

-- ── Tenant role presets table ─────────────────────────────────
CREATE TABLE IF NOT EXISTS tenant_role_presets (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id               TEXT REFERENCES tenants(id) ON DELETE CASCADE,
    role_key                VARCHAR(30) NOT NULL,
    role_label              VARCHAR(100) NOT NULL,
    description             TEXT,
    default_permissions_json JSONB NOT NULL DEFAULT '{}',
    locked_permissions_json  JSONB DEFAULT '{}',
    is_system               BOOLEAN NOT NULL DEFAULT true,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_role_presets_tenant ON tenant_role_presets(tenant_id);
CREATE INDEX IF NOT EXISTS idx_role_presets_key    ON tenant_role_presets(role_key);
ALTER TABLE tenant_role_presets DISABLE ROW LEVEL SECURITY;

-- Insert system default presets (tenant_id NULL = platform defaults)
INSERT INTO tenant_role_presets (role_key, role_label, description, default_permissions_json, locked_permissions_json, is_system)
VALUES
('manager', 'Tenant Manager',
 'Can manage daily tenant operations like a Tenant Admin, except account deletion and billing/payment controls unless specifically allowed.',
 '{"view_dashboard":true,"view_deals":true,"create_deals":true,"edit_deals":true,"update_deal_stages":true,"view_organizations":true,"create_organizations":true,"edit_organizations":true,"view_contacts":true,"create_contacts":true,"edit_contacts":true,"import_contacts":true,"view_referrers":true,"invite_referrers":true,"view_partners":true,"invite_partners":true,"access_import_center":true,"import_deals":true,"view_reports":true,"generate_reports":true,"export_reports":false,"view_messages":true,"send_messages":true,"manage_own_notifications":true,"view_tenant_settings":true,"edit_tenant_profile":false,"invite_tenant_managers":true,"invite_tenant_staff":true,"edit_user_permissions":false,"manage_billing_and_subscription":false,"upgrade_subscription":false,"change_payment_method":false,"view_invoices":false,"delete_deals":false,"delete_contacts":false,"delete_organizations":false,"export_tenant_data":false}',
 '{"delete_tenant_account":false,"transfer_tenant_ownership":false,"remove_tenant_owner":false,"promote_self":false,"edit_own_permissions":false,"bypass_tenant_isolation":false,"modify_lgu_ids_locked_logic":false,"cancel_subscription":false}',
 true
),
('member', 'Tenant Staff',
 'Standard team member with operational access.',
 '{"view_dashboard":true,"view_deals":true,"view_organizations":true,"view_contacts":true,"view_referrers":true,"view_messages":true,"view_reports":true,"manage_own_notifications":true}',
 '{"delete_tenant_account":false,"transfer_tenant_ownership":false,"manage_billing_and_subscription":false,"upgrade_subscription":false,"change_payment_method":false,"invite_tenant_managers":false}',
 true
)
ON CONFLICT DO NOTHING;
