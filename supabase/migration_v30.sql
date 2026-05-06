-- ── Tenant Custom Fields ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS tenant_custom_fields (
    id                UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id         TEXT        NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    destination_type  VARCHAR(30) NOT NULL CHECK (destination_type IN ('deals','contacts','organizations','referrers')),
    field_key         VARCHAR(100) NOT NULL,
    field_label       VARCHAR(200) NOT NULL,
    data_type         VARCHAR(30)  NOT NULL DEFAULT 'text'
                      CHECK (data_type IN ('text','long_text','number','currency','percentage','date','datetime','boolean','email','phone','url','single_select','multi_select')),
    is_required       BOOLEAN NOT NULL DEFAULT false,
    is_importable     BOOLEAN NOT NULL DEFAULT true,
    is_exportable     BOOLEAN NOT NULL DEFAULT true,
    is_visible        BOOLEAN NOT NULL DEFAULT true,
    created_by_user_id UUID REFERENCES tenant_users(id) ON DELETE SET NULL,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (tenant_id, destination_type, field_key)
);
CREATE INDEX IF NOT EXISTS idx_tcf_tenant      ON tenant_custom_fields(tenant_id);
CREATE INDEX IF NOT EXISTS idx_tcf_dest        ON tenant_custom_fields(destination_type);
CREATE INDEX IF NOT EXISTS idx_tcf_tenant_dest ON tenant_custom_fields(tenant_id, destination_type);
ALTER TABLE tenant_custom_fields DISABLE ROW LEVEL SECURITY;

-- ── Tenant Custom Field Values ────────────────────────────────
CREATE TABLE IF NOT EXISTS tenant_custom_field_values (
    id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id        TEXT        NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    custom_field_id  UUID        NOT NULL REFERENCES tenant_custom_fields(id) ON DELETE CASCADE,
    entity_type      VARCHAR(30) NOT NULL CHECK (entity_type IN ('deal','contact','organization')),
    entity_id        TEXT        NOT NULL,
    value_text       TEXT,
    value_number     NUMERIC,
    value_date       DATE,
    value_boolean    BOOLEAN,
    value_json       JSONB,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (custom_field_id, entity_id)
);
CREATE INDEX IF NOT EXISTS idx_tcfv_tenant    ON tenant_custom_field_values(tenant_id);
CREATE INDEX IF NOT EXISTS idx_tcfv_field     ON tenant_custom_field_values(custom_field_id);
CREATE INDEX IF NOT EXISTS idx_tcfv_entity    ON tenant_custom_field_values(entity_type, entity_id);
ALTER TABLE tenant_custom_field_values DISABLE ROW LEVEL SECURITY;

-- ── Tenant Import Templates ───────────────────────────────────
CREATE TABLE IF NOT EXISTS tenant_import_templates (
    id                          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id                   TEXT        NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    destination_type            VARCHAR(30) NOT NULL,
    template_name               VARCHAR(200),
    template_key                VARCHAR(100),
    industry_key                VARCHAR(50),
    is_default                  BOOLEAN     NOT NULL DEFAULT false,
    is_locked                   BOOLEAN     NOT NULL DEFAULT false,
    fields_json                 JSONB       NOT NULL DEFAULT '[]',
    required_fields_json        JSONB       DEFAULT '[]',
    aliases_json                JSONB       DEFAULT '{}',
    sample_headers_json         JSONB       DEFAULT '[]',
    created_from_import_batch_id UUID       REFERENCES import_batches(id) ON DELETE SET NULL,
    created_by_user_id          UUID        REFERENCES tenant_users(id) ON DELETE SET NULL,
    approved_by_user_id         UUID        REFERENCES tenant_users(id) ON DELETE SET NULL,
    double_authenticated_at     TIMESTAMPTZ,
    version_number              INT         NOT NULL DEFAULT 1,
    status                      VARCHAR(20) NOT NULL DEFAULT 'active'
                                CHECK (status IN ('active','archived','draft')),
    created_at                  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at                  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_tit_tenant       ON tenant_import_templates(tenant_id);
CREATE INDEX IF NOT EXISTS idx_tit_dest         ON tenant_import_templates(destination_type);
CREATE INDEX IF NOT EXISTS idx_tit_default      ON tenant_import_templates(tenant_id, destination_type, is_default);
ALTER TABLE tenant_import_templates DISABLE ROW LEVEL SECURITY;

-- ── Extend import_batches with unmapped column tracking ────────
ALTER TABLE import_batches
  ADD COLUMN IF NOT EXISTS unmapped_columns_json   JSONB DEFAULT '[]',
  ADD COLUMN IF NOT EXISTS column_actions_json     JSONB DEFAULT '{}',
  ADD COLUMN IF NOT EXISTS template_adoption_status VARCHAR(30) DEFAULT 'none'
    CHECK (template_adoption_status IN ('none','pending','confirmed','rejected','saved'));
