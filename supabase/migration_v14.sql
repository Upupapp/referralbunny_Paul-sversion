-- ============================================================
-- Referral Bunny — Migration v14
-- Tenant Admin User Authentication
-- ============================================================

-- ── Extend tenants table with new columns ─────────────────────
alter table tenants
  add column if not exists country            text,
  add column if not exists timezone           text not null default 'Asia/Manila',
  add column if not exists preferred_currency text not null default 'PHP',
  add column if not exists website            text,
  add column if not exists setup_completed    boolean not null default false;

-- ── Tenant Admin User Accounts ────────────────────────────────
create table if not exists tenant_users (
  id         uuid    primary key default gen_random_uuid(),
  first_name text    not null,
  last_name  text    not null,
  email      text    not null,
  password   text    not null,
  status     text    not null default 'active'
               check (status in ('active', 'suspended', 'removed')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create unique index if not exists tenant_users_email_lower_idx
  on tenant_users (email);

-- ── Tenant Memberships ────────────────────────────────────────
create table if not exists tenant_memberships (
  id                        uuid    primary key default gen_random_uuid(),
  tenant_id                 text    not null references tenants(id) on delete cascade,
  tenant_user_id            uuid    not null references tenant_users(id) on delete cascade,
  role                      text    not null default 'admin'
                              check (role in ('owner', 'admin', 'member', 'viewer')),
  status                    text    not null default 'active'
                              check (status in ('active', 'suspended', 'removed', 'invited')),
  joined_by_invitation      boolean not null default false,
  password_review_completed boolean not null default true,
  setup_completed           boolean not null default false,
  last_accessed_at          timestamptz,
  joined_at                 timestamptz not null default now(),
  created_at                timestamptz not null default now(),
  updated_at                timestamptz not null default now(),
  unique (tenant_id, tenant_user_id)
);

create index if not exists tenant_memberships_tenant_id_idx  on tenant_memberships (tenant_id);
create index if not exists tenant_memberships_user_id_idx    on tenant_memberships (tenant_user_id);

-- ── Tenant Invitations ────────────────────────────────────────
create table if not exists tenant_invitations (
  id          uuid    primary key default gen_random_uuid(),
  tenant_id   text    not null references tenants(id) on delete cascade,
  email       text    not null,
  role        text    not null default 'admin'
                check (role in ('owner', 'admin', 'member', 'viewer')),
  token       text    not null unique,
  status      text    not null default 'pending'
                check (status in ('pending', 'accepted', 'expired', 'revoked')),
  invited_by  uuid    references tenant_users(id) on delete set null,
  accepted_at timestamptz,
  expires_at  timestamptz not null default (now() + interval '7 days'),
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);

create index if not exists tenant_invitations_token_idx     on tenant_invitations (token);
create index if not exists tenant_invitations_tenant_id_idx on tenant_invitations (tenant_id);
create index if not exists tenant_invitations_email_idx     on tenant_invitations (email);

-- ── Tenant Access Requests ────────────────────────────────────
create table if not exists tenant_access_requests (
  id             uuid primary key default gen_random_uuid(),
  tenant_id      text not null references tenants(id) on delete cascade,
  email          text not null,
  first_name     text not null,
  last_name      text not null,
  requested_role text not null default 'member'
                   check (requested_role in ('admin', 'member', 'viewer')),
  reason         text,
  status         text not null default 'pending'
                   check (status in ('pending', 'approved', 'rejected')),
  reviewed_by    uuid references tenant_users(id) on delete set null,
  reviewed_at    timestamptz,
  created_at     timestamptz not null default now(),
  updated_at     timestamptz not null default now()
);

create index if not exists tenant_access_requests_tenant_id_idx on tenant_access_requests (tenant_id);
create index if not exists tenant_access_requests_email_idx     on tenant_access_requests (email);

-- ── LGU IDS Tenant Bootstrap ──────────────────────────────────
-- Ensure the LGU IDS tenant row exists with full profile.
-- The admin user (Paul@lguids.com.ph) is created via the Laravel seeder
-- using Hash::make() — password is NEVER stored in plain text here.
insert into tenants (
  id, name, slug, program_name, industry,
  admin_email, admin_name, status,
  preferred_currency, country, timezone, setup_completed
) values (
  'lgu-ids',
  'LGU IDS',
  'lgu-ids',
  'LGU IDS Referral Program',
  'Government Technology / GovTech',
  'paul@lguids.com.ph',
  'Paul',
  'active',
  'PHP',
  'Philippines',
  'Asia/Manila',
  true
) on conflict (id) do update set
  name               = excluded.name,
  industry           = excluded.industry,
  admin_email        = excluded.admin_email,
  status             = 'active',
  preferred_currency = 'PHP',
  country            = 'Philippines',
  timezone           = 'Asia/Manila';
