-- ============================================================
-- Referral Bunny — Migration v9
-- Tenant Admin Portal Core Tables
-- ============================================================

-- ── Tenant Roles ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tenant_roles (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name            VARCHAR(100) NOT NULL,
    description     TEXT,
    is_system_role  BOOLEAN DEFAULT false,
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ── Tenant Permissions ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tenant_permissions (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key         VARCHAR(100) UNIQUE NOT NULL,
    module      VARCHAR(100),
    description TEXT,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ── Tenant Role Permissions ───────────────────────────────────
CREATE TABLE IF NOT EXISTS tenant_role_permissions (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_role_id   UUID NOT NULL REFERENCES tenant_roles(id) ON DELETE CASCADE,
    permission_id    UUID NOT NULL REFERENCES tenant_permissions(id) ON DELETE CASCADE,
    created_at       TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (tenant_role_id, permission_id)
);

-- ── Tenant User Roles ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tenant_user_roles (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id         UUID NOT NULL,
    tenant_role_id  UUID NOT NULL REFERENCES tenant_roles(id) ON DELETE CASCADE,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ── Tenant Object Labels ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS tenant_object_labels (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    object_key      VARCHAR(100) NOT NULL,
    singular_label  VARCHAR(200) NOT NULL,
    plural_label    VARCHAR(200) NOT NULL,
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (tenant_id, object_key)
);

-- ── Pipelines ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pipelines (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name        VARCHAR(200) NOT NULL,
    description TEXT,
    is_default  BOOLEAN DEFAULT false,
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    updated_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ── Pipeline Stages ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pipeline_stages (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    pipeline_id     UUID NOT NULL REFERENCES pipelines(id) ON DELETE CASCADE,
    name            VARCHAR(200) NOT NULL,
    description     TEXT,
    order_index     INTEGER NOT NULL DEFAULT 0,
    stage_type      VARCHAR(50),
    is_closed_stage BOOLEAN DEFAULT false,
    is_won_stage    BOOLEAN DEFAULT false,
    is_lost_stage   BOOLEAN DEFAULT false,
    color           VARCHAR(7),
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ── Records ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS records (
    id                   UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id            UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    record_type          VARCHAR(100),
    record_name          VARCHAR(500) NOT NULL,
    record_status        VARCHAR(100) DEFAULT 'active',
    pipeline_id          UUID,
    stage_id             UUID,
    owner_user_id        UUID,
    referrer_id          UUID,
    source               VARCHAR(200),
    priority             VARCHAR(50),
    value_amount         NUMERIC(15,2),
    currency             VARCHAR(3) DEFAULT 'PHP',
    expected_close_date  DATE,
    custom_fields_json   JSONB DEFAULT '{}',
    tags_json            JSONB DEFAULT '[]',
    created_by           UUID,
    updated_by           UUID,
    deleted_at           TIMESTAMPTZ,
    created_at           TIMESTAMPTZ DEFAULT NOW(),
    updated_at           TIMESTAMPTZ DEFAULT NOW()
);

-- ── Contacts ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contacts (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    first_name          VARCHAR(200),
    last_name           VARCHAR(200),
    email               VARCHAR(500),
    phone               VARCHAR(100),
    job_title           VARCHAR(200),
    organization_id     UUID,
    source              VARCHAR(200),
    custom_fields_json  JSONB DEFAULT '{}',
    tags_json           JSONB DEFAULT '[]',
    deleted_at          TIMESTAMPTZ,
    created_at          TIMESTAMPTZ DEFAULT NOW(),
    updated_at          TIMESTAMPTZ DEFAULT NOW()
);

-- ── Organizations ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS organizations (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    organization_name   VARCHAR(500) NOT NULL,
    organization_type   VARCHAR(200),
    website             VARCHAR(500),
    email               VARCHAR(500),
    phone               VARCHAR(100),
    address             TEXT,
    country             VARCHAR(100),
    province_state      VARCHAR(200),
    city                VARCHAR(200),
    custom_fields_json  JSONB DEFAULT '{}',
    tags_json           JSONB DEFAULT '[]',
    deleted_at          TIMESTAMPTZ,
    created_at          TIMESTAMPTZ DEFAULT NOW(),
    updated_at          TIMESTAMPTZ DEFAULT NOW()
);

-- ── Referrer Groups ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS referrer_groups (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name        VARCHAR(200) NOT NULL,
    description TEXT,
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    updated_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ── Referrers ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS referrers (
    id                     UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id              UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name                   VARCHAR(500) NOT NULL,
    email                  VARCHAR(500),
    phone                  VARCHAR(100),
    status                 VARCHAR(50) DEFAULT 'pending',
    referrer_type          VARCHAR(100),
    referral_code          VARCHAR(100),
    group_id               UUID REFERENCES referrer_groups(id) ON DELETE SET NULL,
    commission_profile_id  UUID,
    custom_fields_json     JSONB DEFAULT '{}',
    tags_json              JSONB DEFAULT '[]',
    deleted_at             TIMESTAMPTZ,
    created_at             TIMESTAMPTZ DEFAULT NOW(),
    updated_at             TIMESTAMPTZ DEFAULT NOW()
);

-- ── Custom Fields ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS custom_fields (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id               UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    object_type             VARCHAR(100) NOT NULL,
    field_label             VARCHAR(200) NOT NULL,
    field_key               VARCHAR(200) NOT NULL,
    field_type              VARCHAR(50) NOT NULL,
    is_required             BOOLEAN DEFAULT false,
    options_json            JSONB,
    default_value           TEXT,
    help_text               TEXT,
    display_order           INTEGER DEFAULT 0,
    visible_to_roles_json   JSONB DEFAULT '[]',
    created_at              TIMESTAMPTZ DEFAULT NOW(),
    updated_at              TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (tenant_id, object_type, field_key)
);

-- ── Saved Views ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS saved_views (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id    UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id      UUID,
    object_type  VARCHAR(100) NOT NULL,
    name         VARCHAR(200) NOT NULL,
    filters_json JSONB DEFAULT '{}',
    columns_json JSONB DEFAULT '[]',
    sort_json    JSONB DEFAULT '{}',
    is_shared    BOOLEAN DEFAULT false,
    created_at   TIMESTAMPTZ DEFAULT NOW(),
    updated_at   TIMESTAMPTZ DEFAULT NOW()
);

-- ── Tasks ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tasks (
    id                   UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id            UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    title                VARCHAR(500) NOT NULL,
    description          TEXT,
    linked_object_type   VARCHAR(100),
    linked_object_id     UUID,
    assigned_to_user_id  UUID,
    due_date             TIMESTAMPTZ,
    priority             VARCHAR(50),
    status               VARCHAR(50) DEFAULT 'open',
    created_by           UUID,
    deleted_at           TIMESTAMPTZ,
    created_at           TIMESTAMPTZ DEFAULT NOW(),
    updated_at           TIMESTAMPTZ DEFAULT NOW()
);

-- ── Notes ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notes (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    linked_object_type  VARCHAR(100),
    linked_object_id    UUID,
    note_body           TEXT NOT NULL,
    created_by          UUID,
    deleted_at          TIMESTAMPTZ,
    created_at          TIMESTAMPTZ DEFAULT NOW(),
    updated_at          TIMESTAMPTZ DEFAULT NOW()
);

-- ── Activity Events ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_events (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    linked_object_type  VARCHAR(100),
    linked_object_id    UUID,
    event_type          VARCHAR(200),
    event_description   TEXT,
    old_value_json      JSONB,
    new_value_json      JSONB,
    created_by          UUID,
    created_at          TIMESTAMPTZ DEFAULT NOW()
);

-- ── Files ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS files (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    linked_object_type  VARCHAR(100),
    linked_object_id    UUID,
    file_name           VARCHAR(500),
    file_url            TEXT,
    file_type           VARCHAR(100),
    file_size           BIGINT,
    uploaded_by         UUID,
    created_at          TIMESTAMPTZ DEFAULT NOW()
);

-- ── Message Templates (Tenant-Level) ──────────────────────────
CREATE TABLE IF NOT EXISTS message_templates (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    template_name   VARCHAR(200) NOT NULL,
    channel         VARCHAR(50) NOT NULL,
    subject         VARCHAR(500),
    body            TEXT,
    variables_json  JSONB DEFAULT '[]',
    created_by      UUID,
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ── Tenant Approval Requests ──────────────────────────────────
CREATE TABLE IF NOT EXISTS tenant_approval_requests (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    request_type        VARCHAR(100),
    linked_object_type  VARCHAR(100),
    linked_object_id    UUID,
    requested_by        UUID,
    status              VARCHAR(50) DEFAULT 'pending',
    approver_user_id    UUID,
    notes               TEXT,
    created_at          TIMESTAMPTZ DEFAULT NOW(),
    approved_at         TIMESTAMPTZ,
    rejected_at         TIMESTAMPTZ
);

-- ── Tenant Duplicate Review Items ─────────────────────────────
CREATE TABLE IF NOT EXISTS tenant_duplicate_review_items (
    id                    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id             UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    object_type           VARCHAR(100),
    record_id             UUID,
    possible_duplicate_id UUID,
    match_score           NUMERIC(5,2),
    matched_fields_json   JSONB,
    status                VARCHAR(50) DEFAULT 'pending',
    reviewed_by           UUID,
    reviewed_at           TIMESTAMPTZ,
    created_at            TIMESTAMPTZ DEFAULT NOW()
);

-- ── Tenant Audit Logs ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tenant_audit_logs (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id             UUID,
    role                VARCHAR(200),
    action              VARCHAR(200),
    linked_object_type  VARCHAR(100),
    linked_object_id    UUID,
    old_value_json      JSONB,
    new_value_json      JSONB,
    reason              TEXT,
    created_at          TIMESTAMPTZ DEFAULT NOW()
);

-- ── Tenant User Notification Preferences ─────────────────────
CREATE TABLE IF NOT EXISTS tenant_user_notification_preferences (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id               UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id                 UUID NOT NULL,
    in_app_enabled          BOOLEAN DEFAULT true,
    email_enabled           BOOLEAN DEFAULT true,
    sms_enabled             BOOLEAN DEFAULT false,
    categories_json         JSONB DEFAULT '{}',
    daily_digest_enabled    BOOLEAN DEFAULT false,
    weekly_digest_enabled   BOOLEAN DEFAULT false,
    created_at              TIMESTAMPTZ DEFAULT NOW(),
    updated_at              TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (tenant_id, user_id)
);

-- ============================================================
-- Indexes
-- ============================================================

CREATE INDEX IF NOT EXISTS records_tenant_id_idx      ON records(tenant_id);
CREATE INDEX IF NOT EXISTS records_pipeline_id_idx    ON records(pipeline_id);
CREATE INDEX IF NOT EXISTS records_stage_id_idx       ON records(stage_id);
CREATE INDEX IF NOT EXISTS records_status_idx         ON records(record_status);
CREATE INDEX IF NOT EXISTS records_deleted_at_idx     ON records(deleted_at);

CREATE INDEX IF NOT EXISTS contacts_tenant_id_idx     ON contacts(tenant_id);
CREATE INDEX IF NOT EXISTS contacts_email_idx         ON contacts(email);

CREATE INDEX IF NOT EXISTS organizations_tenant_id_idx ON organizations(tenant_id);

CREATE INDEX IF NOT EXISTS referrers_tenant_id_idx    ON referrers(tenant_id);
CREATE INDEX IF NOT EXISTS referrers_status_idx       ON referrers(status);

CREATE INDEX IF NOT EXISTS tasks_tenant_id_idx             ON tasks(tenant_id);
CREATE INDEX IF NOT EXISTS tasks_linked_object_id_idx      ON tasks(linked_object_id);
CREATE INDEX IF NOT EXISTS tasks_assigned_to_user_id_idx   ON tasks(assigned_to_user_id);
CREATE INDEX IF NOT EXISTS tasks_status_idx                ON tasks(status);

CREATE INDEX IF NOT EXISTS notes_tenant_id_idx         ON notes(tenant_id);
CREATE INDEX IF NOT EXISTS notes_linked_object_id_idx  ON notes(linked_object_id);

CREATE INDEX IF NOT EXISTS activity_events_tenant_id_idx        ON activity_events(tenant_id);
CREATE INDEX IF NOT EXISTS activity_events_linked_object_id_idx ON activity_events(linked_object_id);

CREATE INDEX IF NOT EXISTS custom_fields_tenant_id_idx    ON custom_fields(tenant_id);
CREATE INDEX IF NOT EXISTS custom_fields_object_type_idx  ON custom_fields(object_type);

-- ============================================================
-- Seed: Default Tenant Permissions
-- ============================================================

INSERT INTO tenant_permissions (key, module, description) VALUES
    ('view_dashboard',      'dashboard',  'View the tenant dashboard and KPIs'),
    ('manage_records',      'records',    'Full access to records module'),
    ('create_records',      'records',    'Create new records'),
    ('edit_records',        'records',    'Edit existing records'),
    ('delete_records',      'records',    'Delete records'),
    ('approve_records',     'records',    'Approve records requiring approval'),
    ('assign_records',      'records',    'Assign records to team members'),
    ('export_records',      'records',    'Export records to CSV/Excel'),
    ('import_records',      'records',    'Import records from file'),
    ('manage_referrers',    'referrers',  'Full access to referrers module'),
    ('approve_referrers',   'referrers',  'Approve pending referrer applications'),
    ('manage_commissions',  'commissions','Manage commission rules and profiles'),
    ('approve_commissions', 'commissions','Approve commission payouts'),
    ('view_reports',        'reports',    'View analytics and reports'),
    ('manage_users',        'users',      'Invite and manage team members'),
    ('manage_roles',        'users',      'Manage roles and permissions'),
    ('manage_settings',     'settings',   'Manage tenant settings and configuration'),
    ('view_billing',        'billing',    'View billing and usage information'),
    ('manage_billing',      'billing',    'Manage billing, plans, and payment methods'),
    ('send_messages',       'messages',   'Send messages to referrers and contacts'),
    ('manage_templates',    'messages',   'Create and manage message templates')
ON CONFLICT (key) DO NOTHING;
