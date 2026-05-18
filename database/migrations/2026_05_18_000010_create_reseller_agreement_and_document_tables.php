<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These tables were defined in supabase/migration_v11.sql and migration_v13.sql
        // but were never applied to the production Supabase database. Created here so
        // they exist on any fresh migration run. tenant_id is TEXT (not UUID) to match
        // the tenants.id type in production.

        if (!Schema::hasTable('reseller_agreement_files')) {
            DB::statement("
                CREATE TABLE reseller_agreement_files (
                    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    tenant_id       TEXT NOT NULL,
                    label           VARCHAR(100) NOT NULL,
                    description     TEXT,
                    file_url        TEXT,
                    is_required     BOOLEAN NOT NULL DEFAULT true,
                    version         VARCHAR(20) DEFAULT '1.0',
                    effective_date  DATE,
                    display_order   INTEGER NOT NULL DEFAULT 0,
                    is_active       BOOLEAN NOT NULL DEFAULT true,
                    created_at      TIMESTAMPTZ DEFAULT NOW(),
                    updated_at      TIMESTAMPTZ DEFAULT NOW()
                )
            ");
            DB::statement("CREATE INDEX IF NOT EXISTS idx_agreement_files_tenant ON reseller_agreement_files(tenant_id, is_active)");
        }

        if (!Schema::hasTable('reseller_agreement_acknowledgments')) {
            DB::statement("
                CREATE TABLE reseller_agreement_acknowledgments (
                    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    tenant_id           TEXT NOT NULL,
                    reseller_id         UUID NOT NULL REFERENCES resellers(id) ON DELETE CASCADE,
                    agreement_file_id   UUID NOT NULL REFERENCES reseller_agreement_files(id) ON DELETE CASCADE,
                    agreed_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                    agreed_by_name      VARCHAR(255),
                    ip_address          VARCHAR(45),
                    created_at          TIMESTAMPTZ DEFAULT NOW(),
                    UNIQUE(reseller_id, agreement_file_id)
                )
            ");
            DB::statement("CREATE INDEX IF NOT EXISTS idx_agreement_acks_reseller ON reseller_agreement_acknowledgments(reseller_id)");
            DB::statement("CREATE INDEX IF NOT EXISTS idx_agreement_acks_tenant ON reseller_agreement_acknowledgments(tenant_id)");
        }

        if (!Schema::hasTable('reseller_required_documents')) {
            DB::statement("
                CREATE TABLE reseller_required_documents (
                    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    tenant_id        TEXT NOT NULL,
                    label            VARCHAR(150) NOT NULL,
                    document_type    VARCHAR(100) NOT NULL DEFAULT 'custom',
                    description      TEXT,
                    accepted_formats VARCHAR(255) DEFAULT 'PDF, JPG, PNG',
                    is_required      BOOLEAN NOT NULL DEFAULT true,
                    is_active        BOOLEAN NOT NULL DEFAULT true,
                    display_order    INTEGER NOT NULL DEFAULT 0,
                    created_at       TIMESTAMPTZ DEFAULT NOW(),
                    updated_at       TIMESTAMPTZ DEFAULT NOW()
                )
            ");
            DB::statement("CREATE INDEX IF NOT EXISTS idx_req_docs_tenant ON reseller_required_documents(tenant_id, is_active)");

            // Seed default document for LGU IDS tenant
            DB::table('reseller_required_documents')->insertOrIgnore([[
                'id'              => \Illuminate\Support\Str::uuid(),
                'tenant_id'       => 'lgu-ids',
                'label'           => 'Valid Government ID',
                'document_type'   => 'valid_id',
                'description'     => 'A clear photo or scan of any valid government-issued ID (SSS, PhilHealth, GSIS, Passport, Driver\'s License, Voter\'s ID, etc.)',
                'accepted_formats'=> 'PDF, JPG, PNG',
                'is_required'     => true,
                'display_order'   => 1,
            ]]);
        }

        if (!Schema::hasTable('reseller_document_submissions')) {
            DB::statement("
                CREATE TABLE reseller_document_submissions (
                    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    tenant_id               TEXT NOT NULL,
                    reseller_id             UUID NOT NULL REFERENCES resellers(id) ON DELETE CASCADE,
                    required_document_id    UUID NOT NULL REFERENCES reseller_required_documents(id) ON DELETE CASCADE,
                    file_url                TEXT,
                    file_name               VARCHAR(255),
                    status                  VARCHAR(50) NOT NULL DEFAULT 'not_submitted',
                    submitted_at            TIMESTAMPTZ,
                    reviewed_at             TIMESTAMPTZ,
                    reviewed_by             VARCHAR(255),
                    review_notes            TEXT,
                    created_at              TIMESTAMPTZ DEFAULT NOW(),
                    updated_at              TIMESTAMPTZ DEFAULT NOW(),
                    UNIQUE(reseller_id, required_document_id)
                )
            ");
            DB::statement("CREATE INDEX IF NOT EXISTS idx_doc_subs_reseller ON reseller_document_submissions(reseller_id)");
            DB::statement("CREATE INDEX IF NOT EXISTS idx_doc_subs_tenant ON reseller_document_submissions(tenant_id)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_document_submissions');
        Schema::dropIfExists('reseller_required_documents');
        Schema::dropIfExists('reseller_agreement_acknowledgments');
        Schema::dropIfExists('reseller_agreement_files');
    }
};
