-- ============================================================
-- Referral Bunny — Migration v7
-- Global Search System
-- ============================================================

-- ── Search Index ──────────────────────────────────────────────
create table if not exists search_index (
  id               uuid primary key default gen_random_uuid(),
  entity_type      text not null,
  entity_id        text not null,
  title            text not null,
  description      text,
  keywords         text,
  tags             text[] not null default '{}',
  status           text,
  url              text,
  tenant_id        text references tenants(id) on delete cascade,
  relationships_json jsonb not null default '{}',
  is_deleted       boolean not null default false,
  searchable_text  text not null,
  last_activity_at timestamptz,
  created_at       timestamptz not null default now(),
  updated_at       timestamptz not null default now(),
  unique (entity_type, entity_id)
);

create index if not exists search_index_entity_type_idx    on search_index(entity_type);
create index if not exists search_index_tenant_id_idx      on search_index(tenant_id);
create index if not exists search_index_status_idx         on search_index(status);
create index if not exists search_index_searchable_text_idx on search_index using gin(to_tsvector('english', searchable_text));

-- ── Synonyms ──────────────────────────────────────────────────
create table if not exists synonyms (
  id      uuid primary key default gen_random_uuid(),
  term    text not null,
  synonym text not null,
  unique (term, synonym)
);

insert into synonyms (term, synonym) values
  ('client',        'tenant'),
  ('customer',      'tenant'),
  ('company',       'tenant'),
  ('promo',         'promo_code'),
  ('coupon',        'promo_code'),
  ('discount',      'promo_code'),
  ('unpaid',        'open'),
  ('overdue',       'past_due'),
  ('bill',          'invoice'),
  ('receipt',       'invoice'),
  ('subscription',  'plan'),
  ('account',       'tenant'),
  ('failed',        'failed'),
  ('active',        'active'),
  ('suspended',     'suspended')
on conflict (term, synonym) do nothing;

-- ── Saved Searches ────────────────────────────────────────────
create table if not exists saved_searches (
  id           uuid primary key default gen_random_uuid(),
  user_id      bigint not null references users(id) on delete cascade,
  name         text not null,
  query        text not null,
  filters_json jsonb not null default '{}',
  is_pinned    boolean not null default false,
  created_at   timestamptz not null default now()
);

create index if not exists saved_searches_user_id_idx on saved_searches(user_id);

-- ── Recent Searches ───────────────────────────────────────────
create table if not exists recent_searches (
  id         uuid primary key default gen_random_uuid(),
  user_id    bigint not null references users(id) on delete cascade,
  query      text not null,
  result_count integer not null default 0,
  created_at timestamptz not null default now()
);

create index if not exists recent_searches_user_id_idx  on recent_searches(user_id);
create index if not exists recent_searches_created_idx  on recent_searches(created_at desc);

-- ── Favorites ─────────────────────────────────────────────────
create table if not exists favorites (
  id          uuid primary key default gen_random_uuid(),
  user_id     bigint not null references users(id) on delete cascade,
  entity_type text not null,
  entity_id   text not null,
  label       text,
  url         text,
  created_at  timestamptz not null default now(),
  unique (user_id, entity_type, entity_id)
);

create index if not exists favorites_user_id_idx on favorites(user_id);

-- ── Reindex Jobs ──────────────────────────────────────────────
create table if not exists reindex_jobs (
  id          uuid primary key default gen_random_uuid(),
  entity_type text not null,
  status      text not null default 'pending' check (status in ('pending','running','completed','failed')),
  records_indexed integer not null default 0,
  error       text,
  last_run    timestamptz,
  created_at  timestamptz not null default now()
);

-- ── RLS off ───────────────────────────────────────────────────
alter table search_index    disable row level security;
alter table synonyms        disable row level security;
alter table saved_searches  disable row level security;
alter table recent_searches disable row level security;
alter table favorites       disable row level security;
alter table reindex_jobs    disable row level security;
