-- ============================================================
-- Referral Bunny — Migration v6
-- Pricing Control & Promotion Management
-- ============================================================

-- ── Pricing History ───────────────────────────────────────────
create table if not exists pricing_history (
  id                  uuid primary key default gen_random_uuid(),
  plan_id             uuid not null references plans(id) on delete cascade,
  old_price_monthly   numeric(12,2) not null,
  new_price_monthly   numeric(12,2) not null,
  old_price_yearly    numeric(12,2) not null,
  new_price_yearly    numeric(12,2) not null,
  currency            text not null default 'PHP',
  update_rule         text not null default 'new_subscriptions_only'
                        check (update_rule in ('new_subscriptions_only','next_billing_cycle','immediate_proration','grandfather')),
  change_reason       text,
  effective_date      date not null default current_date,
  changed_by          bigint references users(id),
  approval_request_id uuid,
  created_at          timestamptz not null default now()
);

create index if not exists pricing_history_plan_id_idx  on pricing_history(plan_id);
create index if not exists pricing_history_created_idx  on pricing_history(created_at desc);

-- ── Promo Codes ───────────────────────────────────────────────
create table if not exists promo_codes (
  id                        uuid primary key default gen_random_uuid(),
  code                      text not null unique,
  name                      text not null,
  description               text,
  discount_type             text not null
                              check (discount_type in ('percentage','fixed_amount','free_months','trial_extension')),
  discount_value            numeric(12,2) not null,
  currency                  text not null default 'PHP',
  max_redemptions           integer,
  redemptions_count         integer not null default 0,
  valid_from                timestamptz not null default now(),
  valid_until               timestamptz,
  applies_to_plan_ids_json  jsonb not null default '[]',
  applies_to_billing_cycle  text not null default 'both'
                              check (applies_to_billing_cycle in ('monthly','yearly','both')),
  eligibility_rules_json    jsonb not null default '{}',
  allow_stacking            boolean not null default false,
  status                    text not null default 'active'
                              check (status in ('active','inactive','expired','fully_redeemed','pending_approval')),
  created_by                bigint references users(id),
  created_at                timestamptz not null default now(),
  updated_at                timestamptz not null default now()
);

create index if not exists promo_codes_code_idx    on promo_codes(code);
create index if not exists promo_codes_status_idx  on promo_codes(status);
create index if not exists promo_codes_valid_idx   on promo_codes(valid_from, valid_until);

-- ── Promo Redemptions ─────────────────────────────────────────
create table if not exists promo_redemptions (
  id                 uuid primary key default gen_random_uuid(),
  promo_code_id      uuid not null references promo_codes(id) on delete cascade,
  tenant_id          text not null references tenants(id) on delete cascade,
  subscription_id    uuid references subscriptions(id),
  invoice_id         uuid references invoices(id),
  discount_amount    numeric(12,2) not null,
  currency           text not null default 'PHP',
  exchange_rate_used numeric(18,6) not null default 1,
  redeemed_by        bigint references users(id),
  redeemed_at        timestamptz not null default now()
);

create index if not exists promo_redemptions_promo_id_idx  on promo_redemptions(promo_code_id);
create index if not exists promo_redemptions_tenant_id_idx on promo_redemptions(tenant_id);

-- ── Promo Redemption Attempts (abuse prevention log) ─────────
create table if not exists promo_redemption_attempts (
  id             uuid primary key default gen_random_uuid(),
  promo_code_id  uuid references promo_codes(id) on delete set null,
  code_attempted text not null,
  tenant_id      text references tenants(id) on delete set null,
  failure_reason text not null,
  attempt_data   jsonb not null default '{}',
  attempted_at   timestamptz not null default now()
);

create index if not exists promo_attempts_promo_id_idx  on promo_redemption_attempts(promo_code_id);
create index if not exists promo_attempts_tenant_id_idx on promo_redemption_attempts(tenant_id);
create index if not exists promo_attempts_attempted_idx on promo_redemption_attempts(attempted_at desc);

-- ── Promotions ────────────────────────────────────────────────
create table if not exists promotions (
  id                         uuid primary key default gen_random_uuid(),
  name                       text not null,
  description                text,
  promotion_type             text not null default 'campaign'
                               check (promotion_type in ('campaign','auto_apply','launch','pilot')),
  discount_type              text not null
                               check (discount_type in ('percentage','fixed_amount','free_months','trial_extension')),
  discount_value             numeric(12,2) not null,
  currency                   text not null default 'PHP',
  target_scope               text not null default 'all'
                               check (target_scope in ('all','new_tenants','existing_tenants','specific_tenants','industry','sub_industry')),
  target_ids_json            jsonb not null default '[]',
  applies_to_plan_ids_json   jsonb not null default '[]',
  applies_to_billing_cycles  text[] not null default array['monthly','yearly'],
  valid_from                 timestamptz not null default now(),
  valid_until                timestamptz,
  auto_apply                 boolean not null default false,
  allow_stacking             boolean not null default false,
  max_discounts_per_invoice  integer not null default 1,
  affects_duration           text not null default 'first_invoice'
                               check (affects_duration in ('first_invoice','first_x_months','entire_subscription','next_billing_cycle')),
  duration_months            integer,
  promo_code_id              uuid references promo_codes(id) on delete set null,
  status                     text not null default 'draft'
                               check (status in ('draft','active','paused','ended','pending_approval')),
  created_by                 bigint references users(id),
  created_at                 timestamptz not null default now(),
  updated_at                 timestamptz not null default now()
);

create index if not exists promotions_status_idx     on promotions(status);
create index if not exists promotions_auto_apply_idx on promotions(auto_apply) where auto_apply = true;
create index if not exists promotions_valid_idx      on promotions(valid_from, valid_until);

-- ── Approval Requests ─────────────────────────────────────────
create table if not exists approval_requests (
  id                  uuid primary key default gen_random_uuid(),
  request_type        text not null
                        check (request_type in (
                          'price_change','promo_high_discount','promo_unlimited',
                          'promo_all_tenants','price_decrease','existing_tenant_price_change',
                          'custom_enterprise_pricing','free_plan_modification'
                        )),
  reference_id        text,
  reference_type      text,
  requested_by        bigint not null references users(id),
  required_permission text not null default 'approve_pricing',
  status              text not null default 'pending'
                        check (status in ('pending','approved','rejected')),
  notes               text,
  request_data        jsonb not null default '{}',
  reviewer_notes      text,
  created_at          timestamptz not null default now(),
  approved_by         bigint references users(id),
  approved_at         timestamptz
);

create index if not exists approval_requests_status_idx on approval_requests(status);
create index if not exists approval_requests_type_idx   on approval_requests(request_type);
create index if not exists approval_requests_ref_idx    on approval_requests(reference_id, reference_type);

-- ── Foreign key: pricing_history → approval_requests ─────────
alter table pricing_history
  add constraint pricing_history_approval_fk
  foreign key (approval_request_id) references approval_requests(id) on delete set null;

-- ── RLS off ───────────────────────────────────────────────────
alter table pricing_history             disable row level security;
alter table promo_codes                 disable row level security;
alter table promo_redemptions           disable row level security;
alter table promo_redemption_attempts   disable row level security;
alter table promotions                  disable row level security;
alter table approval_requests           disable row level security;
