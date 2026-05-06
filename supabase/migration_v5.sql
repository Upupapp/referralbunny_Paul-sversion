-- ============================================================
-- Referral Bunny — Migration v5
-- Feature Access Control System
-- ============================================================

-- ── Update plans with correct limits + features ───────────────
update plans set
  plan_limits_json = '{"max_users":3,"max_resellers":5,"max_leads_per_month":100,"max_messages_per_month":0,"max_storage_mb":500,"grace_period_days":7}',
  plan_features_json = '{"messaging":false,"advanced_analytics":false,"template_customization":false,"api_access":false,"sms":false}'
where name = 'Free';

update plans set
  plan_limits_json = '{"max_users":5,"max_resellers":25,"max_leads_per_month":1000,"max_messages_per_month":1000,"max_storage_mb":5000,"grace_period_days":7}',
  plan_features_json = '{"messaging":"limited","advanced_analytics":false,"template_customization":"limited","api_access":false,"sms":false}'
where name = 'Starter';

update plans set
  plan_limits_json = '{"max_users":20,"max_resellers":100,"max_leads_per_month":10000,"max_messages_per_month":10000,"max_storage_mb":20000,"grace_period_days":7}',
  plan_features_json = '{"messaging":true,"advanced_analytics":true,"template_customization":true,"api_access":"limited","sms":"critical_only"}'
where name = 'Pro';

update plans set
  plan_limits_json = '{"max_users":-1,"max_resellers":-1,"max_leads_per_month":-1,"max_messages_per_month":-1,"max_storage_mb":-1,"grace_period_days":14}',
  plan_features_json = '{"messaging":true,"advanced_analytics":true,"template_customization":true,"api_access":true,"sms":"full"}'
where name = 'Enterprise';

-- ── Usage Metrics (monthly billing-period tracking) ───────────
create table if not exists usage_metrics (
  id                    uuid primary key default gen_random_uuid(),
  tenant_id             text not null references tenants(id) on delete cascade,
  billing_period_start  date not null default date_trunc('month', current_date)::date,
  billing_period_end    date not null default (date_trunc('month', current_date) + interval '1 month - 1 day')::date,
  users_count           integer not null default 0,
  resellers_count       integer not null default 0,
  leads_this_month      integer not null default 0,
  messages_this_month   integer not null default 0,
  storage_mb_used       numeric(12,2) not null default 0,
  grace_period_ends_at  timestamptz,
  created_at            timestamptz not null default now(),
  updated_at            timestamptz not null default now(),
  unique (tenant_id, billing_period_start)
);

create index if not exists usage_metrics_tenant_id_idx on usage_metrics(tenant_id);
create index if not exists usage_metrics_period_idx    on usage_metrics(billing_period_start);

-- Seed initial usage metrics for existing tenants
insert into usage_metrics (tenant_id, billing_period_start, billing_period_end)
select
  id,
  date_trunc('month', current_date)::date,
  (date_trunc('month', current_date) + interval '1 month - 1 day')::date
from tenants
on conflict (tenant_id, billing_period_start) do nothing;

-- ── Feature Usage (per-feature tracking) ─────────────────────
create table if not exists feature_usage (
  id           uuid primary key default gen_random_uuid(),
  tenant_id    text not null references tenants(id) on delete cascade,
  feature_name text not null,
  usage_count  integer not null default 0,
  period_start date not null default date_trunc('month', current_date)::date,
  last_used_at timestamptz,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now(),
  unique (tenant_id, feature_name, period_start)
);

create index if not exists feature_usage_tenant_id_idx on feature_usage(tenant_id);
create index if not exists feature_usage_feature_idx   on feature_usage(feature_name);

-- ── Tenant Overrides (permission-controlled) ──────────────────
create table if not exists tenant_overrides (
  id                    uuid primary key default gen_random_uuid(),
  tenant_id             text not null references tenants(id) on delete cascade,
  feature_name          text not null,
  override_value        text not null,
  expires_at            timestamptz,
  approved_by           bigint references users(id),
  approval_reference_id text,
  notes                 text,
  created_at            timestamptz not null default now(),
  updated_at            timestamptz not null default now()
);

create index if not exists tenant_overrides_tenant_id_idx on tenant_overrides(tenant_id);
create index if not exists tenant_overrides_feature_idx   on tenant_overrides(feature_name);
create index if not exists tenant_overrides_expires_idx   on tenant_overrides(expires_at);

-- ── RLS off ───────────────────────────────────────────────────
alter table usage_metrics   disable row level security;
alter table feature_usage   disable row level security;
alter table tenant_overrides disable row level security;
