-- ── Migration v27: tenant_import_settings ────────────────────────────────────
-- Generic per-tenant deal import configuration.
-- LGU IDS uses its own locked import service and does NOT use this table.

CREATE TABLE IF NOT EXISTS tenant_import_settings (
    id                          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id                   TEXT NOT NULL UNIQUE REFERENCES tenants(id) ON DELETE CASCADE,
    industry_template_key       VARCHAR(50)  NOT NULL DEFAULT 'default',
    required_fields             JSONB        NOT NULL DEFAULT '["deal_name","deal_amount","referrer_email","organization_name"]',
    optional_fields             JSONB                 DEFAULT '[]',
    default_stage               VARCHAR(50)           DEFAULT 'introduction',
    default_status              VARCHAR(30)           DEFAULT 'active',
    default_currency            VARCHAR(10)           DEFAULT 'PHP',
    allow_referrer_import       BOOLEAN      NOT NULL DEFAULT true,
    allow_referrer_new_deals    BOOLEAN      NOT NULL DEFAULT false,
    allow_referrer_partner_add  BOOLEAN      NOT NULL DEFAULT false,
    duplicate_handling          VARCHAR(30)  NOT NULL DEFAULT 'require_review'
                                    CHECK (duplicate_handling IN (
                                        'allow', 'block', 'require_review', 'merge_approved', 'overwrite_approved'
                                    )),
    unknown_org_behavior        VARCHAR(30)  NOT NULL DEFAULT 'flag_review'
                                    CHECK (unknown_org_behavior IN (
                                        'auto_create', 'flag_review', 'reject'
                                    )),
    unknown_referrer_behavior   VARCHAR(30)  NOT NULL DEFAULT 'flag_invite'
                                    CHECK (unknown_referrer_behavior IN (
                                        'flag_invite', 'reject'
                                    )),
    unknown_partner_behavior    VARCHAR(30)  NOT NULL DEFAULT 'flag_invite'
                                    CHECK (unknown_partner_behavior IN (
                                        'flag_invite', 'reject'
                                    )),
    lgu_ids_locked              BOOLEAN      NOT NULL DEFAULT false,
    created_at                  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at                  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_tenant_import_settings_tenant
    ON tenant_import_settings(tenant_id);

ALTER TABLE tenant_import_settings DISABLE ROW LEVEL SECURITY;
