-- ── Subscription Tier System + LGU IDS Max (migration_v35) ─────────────────

-- Add plan_key for stable code references (Free→basic, Starter→growth, etc.)
ALTER TABLE plans ADD COLUMN IF NOT EXISTS plan_key TEXT;
ALTER TABLE plans ADD COLUMN IF NOT EXISTS display_order INT NOT NULL DEFAULT 0;
ALTER TABLE plans ADD COLUMN IF NOT EXISTS plan_features_structured JSONB NOT NULL DEFAULT '{}';

-- Rename existing plans to new tier names and assign plan_key
UPDATE plans SET plan_key = 'basic',  name = 'Basic',  display_order = 1
  WHERE LOWER(name) IN ('free','basic');
UPDATE plans SET plan_key = 'growth', name = 'Growth', display_order = 2
  WHERE LOWER(name) IN ('starter','growth');
UPDATE plans SET plan_key = 'pro',    name = 'Pro',    display_order = 3
  WHERE LOWER(name) = 'pro';
UPDATE plans SET plan_key = 'max',    name = 'Max',    display_order = 4
  WHERE LOWER(name) IN ('enterprise','max');

-- Update Basic plan limits (2 users max for the tier spec)
UPDATE plans SET
  plan_limits_json = '{"max_users":2,"max_resellers":1,"max_leads_per_month":50,"max_messages_per_month":0,"max_storage_mb":500,"grace_period_days":7}',
  plan_features_json = '{"messaging":false,"advanced_analytics":false,"template_customization":false,"api_access":false,"sms":false,"bulk_import":false,"export_approval":false,"advanced_commissions":false,"partner_access":false,"custom_fields":2,"audit_logs":false}',
  description = 'For individuals or tiny teams. Up to 2 tenant users and 1 Referrer.'
  WHERE plan_key = 'basic';

-- Update Growth plan limits
UPDATE plans SET
  plan_limits_json = '{"max_users":5,"max_resellers":25,"max_leads_per_month":1000,"max_messages_per_month":1000,"max_storage_mb":5000,"grace_period_days":7}',
  plan_features_json = '{"messaging":"limited","advanced_analytics":false,"template_customization":"limited","api_access":false,"sms":false,"bulk_import":false,"export_approval":true,"advanced_commissions":false,"partner_access":true,"custom_fields":10,"audit_logs":false}',
  description = 'For small teams starting a referral program.',
  price_monthly = 999, price_yearly = 9990
  WHERE plan_key = 'growth';

-- Update Pro plan limits
UPDATE plans SET
  plan_limits_json = '{"max_users":20,"max_resellers":100,"max_leads_per_month":10000,"max_messages_per_month":10000,"max_storage_mb":20000,"grace_period_days":7}',
  plan_features_json = '{"messaging":true,"advanced_analytics":true,"template_customization":"limited","api_access":"limited","sms":"critical_only","bulk_import":true,"export_approval":true,"advanced_commissions":true,"partner_access":true,"custom_fields":50,"audit_logs":true}',
  description = 'For active referral operations with teams and advanced features.',
  price_monthly = 2499, price_yearly = 24990
  WHERE plan_key = 'pro';

-- Insert or ensure Max plan exists
INSERT INTO plans (plan_key, name, description, price_monthly, price_yearly, currency, display_order,
  plan_limits_json, plan_features_json)
VALUES (
  'max', 'Max',
  'The highest standard tier for large or serious referral operations. Highest limits, all features.',
  4999, 49990, 'PHP', 4,
  '{"max_users":-1,"max_resellers":-1,"max_leads_per_month":-1,"max_messages_per_month":-1,"max_storage_mb":-1,"grace_period_days":14}',
  '{"messaging":true,"advanced_analytics":true,"template_customization":true,"api_access":true,"sms":"full","bulk_import":true,"export_approval":true,"advanced_commissions":true,"partner_access":true,"custom_fields":-1,"audit_logs":true,"custom_branding":true,"priority_support":true}'
)
ON CONFLICT DO NOTHING;
-- Also update if it was enterprise/renamed
UPDATE plans SET
  plan_key = 'max', name = 'Max', display_order = 4,
  plan_limits_json = '{"max_users":-1,"max_resellers":-1,"max_leads_per_month":-1,"max_messages_per_month":-1,"max_storage_mb":-1,"grace_period_days":14}',
  plan_features_json = '{"messaging":true,"advanced_analytics":true,"template_customization":true,"api_access":true,"sms":"full","bulk_import":true,"export_approval":true,"advanced_commissions":true,"partner_access":true,"custom_fields":-1,"audit_logs":true,"custom_branding":true,"priority_support":true}',
  description = 'The highest standard tier for large or serious referral operations. Highest limits, all features.',
  price_monthly = 4999, price_yearly = 49990
  WHERE plan_key = 'max' AND name != 'Max';

-- Extend subscription statuses to include internal/comped/manual
ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_status_check;
ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_check
  CHECK (status IN ('trial','active','past_due','limited_access','canceled','suspended','comped','internal','manual','expired'));

-- Add billing metadata columns to subscriptions
ALTER TABLE subscriptions
  ADD COLUMN IF NOT EXISTS billing_status TEXT DEFAULT 'standard',
  ADD COLUMN IF NOT EXISTS manual_note TEXT,
  ADD COLUMN IF NOT EXISTS assigned_by TEXT,
  ADD COLUMN IF NOT EXISTS extended_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS extended_by TEXT,
  ADD COLUMN IF NOT EXISTS extension_reason TEXT,
  ADD COLUMN IF NOT EXISTS subscription_end_date DATE,
  ADD COLUMN IF NOT EXISTS payment_required BOOLEAN NOT NULL DEFAULT true,
  ADD COLUMN IF NOT EXISTS auto_renew BOOLEAN NOT NULL DEFAULT true;

-- ── Set LGU IDS tenant to Max plan for 1 year ──────────────────────────────
-- Find LGU IDS tenant and set subscription to Max (active, comped, 1 year)
DO $$
DECLARE
  v_tenant_id TEXT;
  v_plan_id   UUID;
  v_sub_id    UUID;
  v_start     DATE := CURRENT_DATE;
  v_end       DATE := CURRENT_DATE + INTERVAL '1 year';
BEGIN
  -- Find LGU IDS tenant by slug or name
  SELECT id INTO v_tenant_id FROM tenants
    WHERE slug ILIKE 'lgu%ids' OR LOWER(name) LIKE '%lgu%ids%'
    LIMIT 1;

  IF v_tenant_id IS NULL THEN
    RAISE NOTICE 'LGU IDS tenant not found by slug/name. Skipping subscription grant.';
    RETURN;
  END IF;

  -- Find Max plan
  SELECT id INTO v_plan_id FROM plans WHERE plan_key = 'max' LIMIT 1;
  IF v_plan_id IS NULL THEN
    SELECT id INTO v_plan_id FROM plans WHERE LOWER(name) = 'max' LIMIT 1;
  END IF;

  IF v_plan_id IS NULL THEN
    RAISE NOTICE 'Max plan not found. Skipping.';
    RETURN;
  END IF;

  -- Upsert subscription for LGU IDS
  SELECT id INTO v_sub_id FROM subscriptions WHERE tenant_id = v_tenant_id LIMIT 1;

  IF v_sub_id IS NOT NULL THEN
    UPDATE subscriptions SET
      plan_id              = v_plan_id,
      status               = 'active',
      billing_status       = 'internal',
      billing_cycle        = 'yearly',
      start_date           = v_start,
      subscription_end_date = v_end,
      next_billing_date    = v_end,
      trial_end_date       = NULL,
      canceled_at          = NULL,
      payment_required     = false,
      auto_renew           = false,
      manual_note          = 'LGU IDS granted 1-year Max subscription by platform owner instruction (migration_v35).',
      assigned_by          = 'system_migration',
      updated_at           = NOW()
    WHERE id = v_sub_id;
    RAISE NOTICE 'Updated LGU IDS subscription to Max (id=%). End date: %', v_sub_id, v_end;
  ELSE
    INSERT INTO subscriptions (
      tenant_id, plan_id, status, billing_status, billing_cycle,
      start_date, subscription_end_date, next_billing_date,
      payment_required, auto_renew, manual_note, assigned_by
    ) VALUES (
      v_tenant_id, v_plan_id, 'active', 'internal', 'yearly',
      v_start, v_end, v_end,
      false, false,
      'LGU IDS granted 1-year Max subscription by platform owner instruction (migration_v35).',
      'system_migration'
    );
    RAISE NOTICE 'Created new Max subscription for LGU IDS. End date: %', v_end;
  END IF;
END $$;
