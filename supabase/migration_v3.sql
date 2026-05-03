-- ============================================================
-- Referral Bunny — Migration v3
-- Super Admin Intelligence + Notifications
-- ============================================================

-- ── Tenant Metrics ────────────────────────────────────────────
create table if not exists tenant_metrics (
  id                          uuid primary key default gen_random_uuid(),
  tenant_id                   text not null references tenants(id) on delete cascade,
  health_score                integer not null default 0 check (health_score between 0 and 100),
  health_level                text not null default 'at_risk' check (health_level in ('healthy','needs_attention','at_risk')),
  last_activity_at            timestamptz,
  active_users_count          integer not null default 0,
  inactive_users_count        integer not null default 0,
  leads_count                 integer not null default 0,
  stale_leads_count           integer not null default 0,
  setup_completion_percentage integer not null default 0 check (setup_completion_percentage between 0 and 100),
  payment_status              text not null default 'unknown' check (payment_status in ('paid','overdue','failed','unknown')),
  has_payment_method          boolean not null default false,
  trial_days_remaining        integer,
  subscription_status         text not null default 'trial' check (subscription_status in ('trial','active','past_due','cancelled','suspended')),
  last_payment_date           date,
  -- Engagement metrics
  dau                         integer not null default 0,
  wau                         integer not null default 0,
  mau                         integer not null default 0,
  -- Billing (stub for Instruction 3)
  mrr                         numeric(12,2) not null default 0,
  arr                         numeric(12,2) not null default 0,
  created_at                  timestamptz not null default now(),
  updated_at                  timestamptz not null default now(),
  unique (tenant_id)
);

create index if not exists tenant_metrics_health_level_idx on tenant_metrics(health_level);
create index if not exists tenant_metrics_tenant_id_idx on tenant_metrics(tenant_id);

-- Seed initial metrics for existing tenants
insert into tenant_metrics (tenant_id, health_score, health_level, leads_count, setup_completion_percentage, subscription_status)
select id, 75, 'needs_attention', 0, 60, 'trial'
from tenants
on conflict (tenant_id) do nothing;

-- ── Notifications ─────────────────────────────────────────────
create table if not exists notifications (
  id               uuid primary key default gen_random_uuid(),
  tenant_id        text references tenants(id) on delete cascade,
  category         text not null check (category in ('billing','tenant_health','analytics','messaging','system','growth','approvals')),
  type             text not null check (type in ('info','warning','action_required','system_alert')),
  priority         text not null default 'low' check (priority in ('low','medium','high','critical')),
  message          text not null,
  action_url       text,
  channel          text not null default 'in_app' check (channel in ('in_app','email','sms','all')),
  frequency_type   text not null default 'instant' check (frequency_type in ('instant','daily','weekly','monthly')),
  escalation_level integer not null default 0,
  is_read          boolean not null default false,
  is_dismissed     boolean not null default false,
  metadata_json    jsonb not null default '{}',
  sent_at          timestamptz,
  created_at       timestamptz not null default now()
);

create index if not exists notifications_tenant_id_idx  on notifications(tenant_id);
create index if not exists notifications_priority_idx   on notifications(priority);
create index if not exists notifications_is_read_idx    on notifications(is_read);
create index if not exists notifications_category_idx   on notifications(category);
create index if not exists notifications_created_at_idx on notifications(created_at desc);

-- ── User Notification Preferences ────────────────────────────
create table if not exists user_notification_preferences (
  id                      uuid primary key default gen_random_uuid(),
  user_id                 bigint not null references users(id) on delete cascade,
  in_app_enabled          boolean not null default true,
  email_enabled           boolean not null default true,
  sms_enabled             boolean not null default false,
  daily_digest_enabled    boolean not null default false,
  weekly_digest_enabled   boolean not null default true,
  monthly_report_enabled  boolean not null default true,
  critical_alerts_only    boolean not null default false,
  categories_json         jsonb not null default '["billing","tenant_health","analytics","messaging","system","growth","approvals"]',
  created_at              timestamptz not null default now(),
  updated_at              timestamptz not null default now(),
  unique (user_id)
);

-- Seed preferences for existing super admin
insert into user_notification_preferences (user_id)
select id from users where email = 'admin@referralbunny.com'
on conflict (user_id) do nothing;

-- ── Activity Log ──────────────────────────────────────────────
create table if not exists activity_logs (
  id         uuid primary key default gen_random_uuid(),
  tenant_id  text references tenants(id) on delete cascade,
  user_id    bigint references users(id) on delete set null,
  action     text not null,
  entity     text,
  entity_id  text,
  metadata   jsonb not null default '{}',
  created_at timestamptz not null default now()
);

create index if not exists activity_logs_tenant_id_idx  on activity_logs(tenant_id);
create index if not exists activity_logs_created_at_idx on activity_logs(created_at desc);

-- ── RLS off ───────────────────────────────────────────────────
alter table tenant_metrics                  disable row level security;
alter table notifications                   disable row level security;
alter table user_notification_preferences   disable row level security;
alter table activity_logs                   disable row level security;
