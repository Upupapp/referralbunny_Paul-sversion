-- ============================================================
-- Referral Bunny — Migration v8
-- Import Center & Data Import System
-- ============================================================

-- ── Import Templates ──────────────────────────────────────────
create table if not exists import_templates (
  id                      uuid primary key default gen_random_uuid(),
  object_type             text not null,
  template_name           text not null,
  version                 text not null default '1.0',
  description             text,
  required_columns_json   jsonb not null default '[]',
  optional_columns_json   jsonb not null default '[]',
  accepted_values_json    jsonb not null default '{}',
  sample_file_url         text,
  is_active               boolean not null default true,
  created_by              bigint references users(id),
  created_at              timestamptz not null default now(),
  updated_at              timestamptz not null default now()
);

create index if not exists import_templates_object_type_idx on import_templates(object_type);

-- ── Import Jobs ───────────────────────────────────────────────
create table if not exists import_jobs (
  id                            uuid primary key default gen_random_uuid(),
  import_name                   text not null,
  import_type                   text not null default 'quick'
                                  check (import_type in ('quick','advanced','migration','sandbox')),
  object_type                   text not null,
  tenant_id                     text references tenants(id) on delete set null,
  uploaded_by                   bigint not null references users(id),
  file_name                     text,
  file_size                     bigint,
  file_type                     text,
  source_type                   text not null default 'file_upload'
                                  check (source_type in ('file_upload','pasted_spreadsheet','api','system_migration')),
  template_version              text,
  status                        text not null default 'uploaded'
                                  check (status in (
                                    'uploaded','parsing','structure_validating','mapping_required',
                                    'validating_rows','ready_for_review','waiting_for_approval',
                                    'approved','importing','completed','completed_with_errors',
                                    'failed','canceled','rolled_back','sandbox_completed'
                                  )),
  total_rows                    integer not null default 0,
  processed_rows                integer not null default 0,
  successful_rows               integer not null default 0,
  failed_rows                   integer not null default 0,
  skipped_rows                  integer not null default 0,
  warning_rows                  integer not null default 0,
  overwritten_fields_count      integer not null default 0,
  cleared_fields_count          integer not null default 0,
  import_mode                   text not null default 'upsert'
                                  check (import_mode in ('create_only','update_only','upsert','validate_only')),
  overwrite_mode                text not null default 'overwrite_mapped'
                                  check (overwrite_mode in ('skip_existing','update_blank_only','overwrite_mapped','overwrite_all','allow_clear')),
  risk_level                    text not null default 'low'
                                  check (risk_level in ('low','medium','high','critical')),
  approval_required             boolean not null default false,
  approval_request_id           uuid references approval_requests(id),
  column_mapping_json           jsonb not null default '{}',
  headers_detected_json         jsonb not null default '[]',
  file_language                 text default 'en',
  date_format                   text default 'YYYY-MM-DD',
  number_format                 text default 'en',
  timezone                      text default 'Asia/Manila',
  detected_encoding             text,
  selected_encoding             text default 'UTF-8',
  communication_consent_confirmed boolean not null default false,
  consent_confirmed_by          bigint references users(id),
  consent_confirmed_at          timestamptz,
  automation_triggers_json      jsonb not null default '{}',
  migration_mode_enabled        boolean not null default false,
  rollback_available_until      timestamptz,
  rollback_status               text default 'available'
                                  check (rollback_status in ('available','requires_review','expired','not_safe','completed')),
  last_processed_row            integer default 0,
  resumable                     boolean not null default true,
  error_summary_json            jsonb not null default '{}',
  raw_file_path                 text,
  started_at                    timestamptz,
  completed_at                  timestamptz,
  canceled_at                   timestamptz,
  created_at                    timestamptz not null default now(),
  updated_at                    timestamptz not null default now()
);

create index if not exists import_jobs_status_idx      on import_jobs(status);
create index if not exists import_jobs_object_type_idx on import_jobs(object_type);
create index if not exists import_jobs_uploaded_by_idx on import_jobs(uploaded_by);
create index if not exists import_jobs_tenant_id_idx   on import_jobs(tenant_id);
create index if not exists import_jobs_created_idx     on import_jobs(created_at desc);

-- ── Import Rows ───────────────────────────────────────────────
create table if not exists import_rows (
  id                 uuid primary key default gen_random_uuid(),
  import_job_id      uuid not null references import_jobs(id) on delete cascade,
  row_number         integer not null,
  raw_data_json      jsonb not null default '{}',
  mapped_data_json   jsonb not null default '{}',
  matched_entity_type text,
  matched_entity_id  text,
  status             text not null default 'pending'
                       check (status in ('pending','valid_new','valid_update','warning','error','skipped','imported','failed')),
  error_count        integer not null default 0,
  warning_count      integer not null default 0,
  created_entity_id  text,
  updated_entity_id  text,
  created_at         timestamptz not null default now(),
  updated_at         timestamptz not null default now()
);

create index if not exists import_rows_job_id_idx on import_rows(import_job_id);
create index if not exists import_rows_status_idx on import_rows(status);

-- ── Import Row Errors ─────────────────────────────────────────
create table if not exists import_row_errors (
  id              uuid primary key default gen_random_uuid(),
  import_job_id   uuid not null references import_jobs(id) on delete cascade,
  import_row_id   uuid references import_rows(id) on delete cascade,
  row_number      integer not null,
  column_name     text,
  error_type      text not null,
  error_message   text not null,
  severity        text not null default 'blocking'
                    check (severity in ('warning','blocking')),
  suggested_fix   text,
  created_at      timestamptz not null default now()
);

create index if not exists import_row_errors_job_id_idx on import_row_errors(import_job_id);

-- ── Import Field Changes ──────────────────────────────────────
create table if not exists import_field_changes (
  id             uuid primary key default gen_random_uuid(),
  import_job_id  uuid not null references import_jobs(id) on delete cascade,
  import_row_id  uuid references import_rows(id) on delete cascade,
  entity_type    text not null,
  entity_id      text,
  field_name     text not null,
  old_value      text,
  new_value      text,
  change_type    text not null
                   check (change_type in ('new','unchanged','overwritten','cleared','skipped','warning','error')),
  color_code     text,
  will_overwrite boolean not null default false,
  created_at     timestamptz not null default now()
);

create index if not exists import_field_changes_job_id_idx on import_field_changes(import_job_id);

-- ── Import Mapping Profiles ───────────────────────────────────
create table if not exists import_mapping_profiles (
  id                  uuid primary key default gen_random_uuid(),
  user_id             bigint not null references users(id) on delete cascade,
  tenant_id           text references tenants(id) on delete set null,
  object_type         text not null,
  profile_name        text not null,
  mapping_json        jsonb not null default '{}',
  header_fingerprint  text,
  created_at          timestamptz not null default now(),
  updated_at          timestamptz not null default now()
);

create index if not exists import_mapping_profiles_user_idx on import_mapping_profiles(user_id);

-- ── Import Approval Steps ─────────────────────────────────────
create table if not exists import_approval_steps (
  id                   uuid primary key default gen_random_uuid(),
  approval_request_id  uuid references approval_requests(id) on delete cascade,
  import_job_id        uuid references import_jobs(id) on delete cascade,
  approver_user_id     bigint references users(id),
  approver_role        text,
  approval_order       integer not null default 1,
  status               text not null default 'pending'
                         check (status in ('pending','approved','rejected','skipped')),
  notes                text,
  approved_at          timestamptz,
  created_at           timestamptz not null default now()
);

-- ── Import Rollbacks ──────────────────────────────────────────
create table if not exists import_rollbacks (
  id                    uuid primary key default gen_random_uuid(),
  import_job_id         uuid not null references import_jobs(id) on delete cascade,
  requested_by          bigint references users(id),
  status                text not null default 'pending'
                          check (status in ('pending','running','completed','failed','partial')),
  rollback_summary_json jsonb not null default '{}',
  records_restored      integer not null default 0,
  records_deleted       integer not null default 0,
  created_at            timestamptz not null default now(),
  completed_at          timestamptz
);

-- ── Import Snapshots ──────────────────────────────────────────
create table if not exists import_snapshots (
  id              uuid primary key default gen_random_uuid(),
  import_job_id   uuid not null references import_jobs(id) on delete cascade,
  entity_type     text not null,
  entity_id       text not null,
  old_values_json jsonb not null default '{}',
  new_values_json jsonb not null default '{}',
  action          text not null check (action in ('created','updated')),
  created_at      timestamptz not null default now()
);

create index if not exists import_snapshots_job_id_idx on import_snapshots(import_job_id);
create index if not exists import_snapshots_entity_idx on import_snapshots(entity_type, entity_id);

-- ── Duplicate Review Items ────────────────────────────────────
create table if not exists duplicate_review_items (
  id                        uuid primary key default gen_random_uuid(),
  import_job_id             uuid not null references import_jobs(id) on delete cascade,
  object_type               text not null,
  uploaded_row_data_json    jsonb not null default '{}',
  possible_match_entity_id  text,
  possible_match_data_json  jsonb not null default '{}',
  match_score               integer not null default 0,
  matched_fields_json       jsonb not null default '[]',
  status                    text not null default 'pending'
                              check (status in ('pending','confirmed_duplicate','not_duplicate','merged','skipped')),
  reviewed_by               bigint references users(id),
  reviewed_at               timestamptz,
  created_at                timestamptz not null default now()
);

create index if not exists duplicate_review_items_job_id_idx on duplicate_review_items(import_job_id);
create index if not exists duplicate_review_items_status_idx on duplicate_review_items(status);

-- ── Import Idempotency ────────────────────────────────────────
create table if not exists import_idempotency (
  id             uuid primary key default gen_random_uuid(),
  import_job_id  uuid references import_jobs(id) on delete cascade,
  row_hash       text not null,
  entity_type    text not null,
  entity_id      text,
  created_at     timestamptz not null default now(),
  unique (row_hash, entity_type)
);

-- ── Seed default import templates ─────────────────────────────
insert into import_templates (object_type, template_name, version, description, required_columns_json, optional_columns_json) values
('tenant', 'Standard Tenants Import', '1.0', 'Import organizations (tenants) into the platform.',
  '["tenant_name","industry","primary_admin_email"]',
  '["tenant_slug","legal_name","website","primary_admin_first_name","primary_admin_last_name","primary_admin_phone","billing_email","phone_number","country","city","preferred_currency","plan_name","subscription_status","trial_days","tenant_status","tags","notes","source"]'),
('reseller', 'Standard Resellers Import', '1.0', 'Import resellers / referral members for a tenant.',
  '["tenant_identifier","reseller_name","reseller_email"]',
  '["reseller_phone","reseller_type","reseller_status","referral_code","commission_profile","assigned_region","tags","notes","invite_reseller","external_reseller_id"]'),
('lead', 'Standard Leads Import', '1.0', 'Import leads / referral records for a tenant.',
  '["tenant_identifier","lead_name","lead_status"]',
  '["lead_email","lead_phone","lead_external_id","company_name","source","referred_by_reseller_email","deal_value","currency","expected_close_date","notes","tags"]'),
('custom_field', 'Standard Custom Fields Import', '1.0', 'Import custom field definitions for a tenant.',
  '["tenant_identifier","field_label","field_key","field_type"]',
  '["required","options","default_value","visible_to_admin","visible_to_reseller","display_order","applies_to_object","help_text"]'),
('tag', 'Standard Tags Import', '1.0', 'Import tags for use across tenant records.',
  '["tenant_identifier","tag_name","applies_to_object"]',
  '["color","description","status"]')
on conflict do nothing;

-- ── RLS off ───────────────────────────────────────────────────
alter table import_templates          disable row level security;
alter table import_jobs               disable row level security;
alter table import_rows               disable row level security;
alter table import_row_errors         disable row level security;
alter table import_field_changes      disable row level security;
alter table import_mapping_profiles   disable row level security;
alter table import_approval_steps     disable row level security;
alter table import_rollbacks          disable row level security;
alter table import_snapshots          disable row level security;
alter table duplicate_review_items    disable row level security;
alter table import_idempotency        disable row level security;
