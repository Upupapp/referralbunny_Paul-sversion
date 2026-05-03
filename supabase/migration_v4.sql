-- ============================================================
-- Referral Bunny — Migration v4
-- Billing, Subscription & Payment System
-- ============================================================

-- ── Extend tenants ────────────────────────────────────────────
alter table tenants
  add column if not exists preferred_currency text not null default 'PHP';

-- ── Plans ─────────────────────────────────────────────────────
create table if not exists plans (
  id                    uuid primary key default gen_random_uuid(),
  name                  text not null,
  description           text,
  price_monthly         numeric(12,2) not null default 0,
  price_yearly          numeric(12,2) not null default 0,
  currency              text not null default 'PHP',
  billing_cycle_options text[] not null default array['monthly','yearly'],
  plan_limits_json      jsonb not null default '{}',
  plan_features_json    jsonb not null default '[]',
  is_active             boolean not null default true,
  created_at            timestamptz not null default now(),
  updated_at            timestamptz not null default now()
);

-- Seed default plans
insert into plans (name, description, price_monthly, price_yearly, currency, plan_limits_json, plan_features_json) values
(
  'Free',
  'Get started at no cost. Limited features.',
  0, 0, 'PHP',
  '{"max_leads":10,"max_resellers":2,"max_users":1}',
  '["Lead management","Basic reports","Email support"]'
),
(
  'Starter',
  'For small teams getting started with referral programs.',
  999, 9990, 'PHP',
  '{"max_leads":100,"max_resellers":10,"max_users":3}',
  '["All Free features","Messaging center","CSV exports","Priority support"]'
),
(
  'Pro',
  'For growing businesses with active referral pipelines.',
  2499, 24990, 'PHP',
  '{"max_leads":1000,"max_resellers":50,"max_users":10}',
  '["All Starter features","Analytics engine","Industry benchmarks","SMS notifications","Custom pipeline stages"]'
),
(
  'Enterprise',
  'Custom pricing for large organizations.',
  0, 0, 'PHP',
  '{"max_leads":-1,"max_resellers":-1,"max_users":-1}',
  '["All Pro features","Custom pricing","Dedicated support","SLA","White-labeling"]'
);

-- ── Exchange Rates ────────────────────────────────────────────
create table if not exists exchange_rates (
  id              uuid primary key default gen_random_uuid(),
  base_currency   text not null default 'PHP',
  target_currency text not null,
  rate            numeric(18,6) not null,
  source          text not null default 'manual',
  updated_at      timestamptz not null default now(),
  unique (base_currency, target_currency)
);

insert into exchange_rates (base_currency, target_currency, rate, source) values
('PHP', 'USD', 0.017400, 'manual'),
('PHP', 'SGD', 0.023500, 'manual'),
('PHP', 'EUR', 0.016200, 'manual'),
('USD', 'PHP', 57.47000, 'manual'),
('SGD', 'PHP', 42.55000, 'manual'),
('EUR', 'PHP', 61.73000, 'manual')
on conflict (base_currency, target_currency) do nothing;

-- ── Subscriptions ─────────────────────────────────────────────
create table if not exists subscriptions (
  id                       uuid primary key default gen_random_uuid(),
  tenant_id                text not null references tenants(id) on delete cascade,
  plan_id                  uuid references plans(id),
  status                   text not null default 'trial'
                             check (status in ('trial','active','past_due','limited_access','canceled','suspended')),
  billing_cycle            text not null default 'monthly'
                             check (billing_cycle in ('monthly','yearly')),
  start_date               date not null default current_date,
  trial_end_date           date,
  next_billing_date        date,
  canceled_at              timestamptz,
  external_subscription_id text,
  payment_provider         text default 'paymongo',
  created_at               timestamptz not null default now(),
  updated_at               timestamptz not null default now()
);

create index if not exists subscriptions_tenant_id_idx on subscriptions(tenant_id);
create index if not exists subscriptions_status_idx    on subscriptions(status);

-- Auto-create trial subscriptions for existing tenants
insert into subscriptions (tenant_id, status, trial_end_date, next_billing_date)
select id, 'trial', current_date + interval '15 days', current_date + interval '15 days'
from tenants
where id not in (select tenant_id from subscriptions)
on conflict do nothing;

-- ── Payment Methods ───────────────────────────────────────────
create table if not exists payment_methods (
  id                          uuid primary key default gen_random_uuid(),
  tenant_id                   text not null references tenants(id) on delete cascade,
  provider                    text not null default 'paymongo',
  external_payment_method_id  text,
  type                        text not null default 'card'
                                check (type in ('card','gcash','maya','bank_transfer','grab_pay')),
  last_four                   text,
  brand                       text,
  is_default                  boolean not null default false,
  created_at                  timestamptz not null default now()
);

create index if not exists payment_methods_tenant_id_idx on payment_methods(tenant_id);

-- ── Invoices ──────────────────────────────────────────────────
create table if not exists invoices (
  id                  uuid primary key default gen_random_uuid(),
  tenant_id           text not null references tenants(id) on delete cascade,
  subscription_id     uuid references subscriptions(id),
  invoice_number      text not null unique,
  base_amount_php     numeric(12,2) not null default 0,
  display_amount      numeric(12,2) not null default 0,
  display_currency    text not null default 'PHP',
  exchange_rate_used  numeric(18,6) not null default 1,
  tax_rate            numeric(5,2) not null default 0,
  tax_type            text not null default 'exclusive' check (tax_type in ('inclusive','exclusive')),
  tax_amount          numeric(12,2) not null default 0,
  discount_amount     numeric(12,2) not null default 0,
  credits_applied     numeric(12,2) not null default 0,
  final_amount        numeric(12,2) not null default 0,
  status              text not null default 'draft'
                        check (status in ('draft','open','paid','void','waived','past_due')),
  due_date            date,
  paid_at             timestamptz,
  line_items_json     jsonb not null default '[]',
  notes               text,
  created_at          timestamptz not null default now(),
  updated_at          timestamptz not null default now()
);

create index if not exists invoices_tenant_id_idx on invoices(tenant_id);
create index if not exists invoices_status_idx    on invoices(status);

-- ── Payments ──────────────────────────────────────────────────
create table if not exists payments (
  id                    uuid primary key default gen_random_uuid(),
  tenant_id             text not null references tenants(id) on delete cascade,
  subscription_id       uuid references subscriptions(id),
  invoice_id            uuid references invoices(id),
  plan_id               uuid references plans(id),
  provider              text not null default 'paymongo',
  amount                numeric(12,2) not null,
  currency              text not null default 'PHP',
  base_amount_php       numeric(12,2) not null,
  display_amount        numeric(12,2),
  display_currency      text default 'PHP',
  exchange_rate_used    numeric(18,6) not null default 1,
  status                text not null default 'pending'
                          check (status in ('pending','processing','paid','failed','refunded','voided')),
  external_payment_id   text,
  payment_method        text,
  failure_reason        text,
  retry_count           integer not null default 0,
  next_retry_at         timestamptz,
  metadata_json         jsonb not null default '{}',
  created_at            timestamptz not null default now(),
  updated_at            timestamptz not null default now()
);

create index if not exists payments_tenant_id_idx on payments(tenant_id);
create index if not exists payments_status_idx    on payments(status);

-- ── Refunds ───────────────────────────────────────────────────
create table if not exists refunds (
  id           uuid primary key default gen_random_uuid(),
  payment_id   uuid not null references payments(id),
  tenant_id    text not null references tenants(id) on delete cascade,
  amount       numeric(12,2) not null,
  currency     text not null default 'PHP',
  reason       text not null,
  status       text not null default 'pending'
                 check (status in ('pending','approved','processed','rejected')),
  processed_by bigint references users(id),
  approved_by  bigint references users(id),
  notes        text,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);

-- ── Credits ───────────────────────────────────────────────────
create table if not exists credits (
  id                    uuid primary key default gen_random_uuid(),
  tenant_id             text not null references tenants(id) on delete cascade,
  amount                numeric(12,2) not null,
  currency              text not null default 'PHP',
  reason                text not null,
  applied_to_invoice_id uuid references invoices(id),
  created_by            bigint references users(id),
  created_at            timestamptz not null default now()
);

create index if not exists credits_tenant_id_idx on credits(tenant_id);

-- ── Custom Pricing ────────────────────────────────────────────
create table if not exists tenant_custom_pricing (
  id                   uuid primary key default gen_random_uuid(),
  tenant_id            text not null references tenants(id) on delete cascade,
  plan_id              uuid not null references plans(id),
  custom_price_monthly numeric(12,2) not null,
  custom_price_yearly  numeric(12,2) not null,
  currency             text not null default 'PHP',
  effective_from       date not null default current_date,
  effective_until      date,
  approved_by          bigint references users(id),
  notes                text,
  created_at           timestamptz not null default now()
);

-- ── Add-ons ───────────────────────────────────────────────────
create table if not exists addons (
  id           uuid primary key default gen_random_uuid(),
  name         text not null,
  description  text,
  price        numeric(12,2) not null default 0,
  currency     text not null default 'PHP',
  billing_type text not null default 'monthly'
                 check (billing_type in ('monthly','yearly','one_time')),
  is_active    boolean not null default true,
  created_at   timestamptz not null default now()
);

create table if not exists tenant_addons (
  id         uuid primary key default gen_random_uuid(),
  tenant_id  text not null references tenants(id) on delete cascade,
  addon_id   uuid not null references addons(id),
  quantity   integer not null default 1,
  created_at timestamptz not null default now()
);

-- ── Subscription History ──────────────────────────────────────
create table if not exists subscription_history (
  id          uuid primary key default gen_random_uuid(),
  tenant_id   text not null references tenants(id) on delete cascade,
  old_plan_id uuid references plans(id),
  new_plan_id uuid references plans(id),
  old_status  text,
  new_status  text,
  changed_by  bigint references users(id),
  reason      text,
  changed_at  timestamptz not null default now()
);

-- ── Webhook Events ────────────────────────────────────────────
create table if not exists webhook_events (
  id          uuid primary key default gen_random_uuid(),
  provider    text not null default 'paymongo',
  event_type  text not null,
  payload     jsonb not null default '{}',
  processed   boolean not null default false,
  error       text,
  created_at  timestamptz not null default now()
);

create index if not exists webhook_events_processed_idx on webhook_events(processed);

-- ── Billing Audit Log ─────────────────────────────────────────
create table if not exists billing_audit_logs (
  id          uuid primary key default gen_random_uuid(),
  tenant_id   text references tenants(id),
  action      text not null,
  entity_type text,
  entity_id   text,
  performed_by bigint references users(id),
  reason      text,
  before_json jsonb default '{}',
  after_json  jsonb default '{}',
  created_at  timestamptz not null default now()
);

create index if not exists billing_audit_logs_tenant_id_idx on billing_audit_logs(tenant_id);

-- ── RLS off ───────────────────────────────────────────────────
alter table plans                  disable row level security;
alter table exchange_rates         disable row level security;
alter table subscriptions          disable row level security;
alter table payment_methods        disable row level security;
alter table invoices               disable row level security;
alter table payments               disable row level security;
alter table refunds                disable row level security;
alter table credits                disable row level security;
alter table tenant_custom_pricing  disable row level security;
alter table addons                 disable row level security;
alter table tenant_addons          disable row level security;
alter table subscription_history   disable row level security;
alter table webhook_events         disable row level security;
alter table billing_audit_logs     disable row level security;
