<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // One active (status='draft') or published wizard draft per tenant at a time.
        // The whole 15-step wizard config is stored as a single JSON document; on
        // publish, the relevant slices are synced into tenant_program_configs /
        // tenant_pipeline_stages / tenant_custom_fields / tenant_import_templates
        // so existing runtime code keeps reading what it already reads.
        DB::statement("
            CREATE TABLE IF NOT EXISTS tenant_referral_program_drafts (
                id           TEXT        PRIMARY KEY DEFAULT gen_random_uuid()::TEXT,
                tenant_id    TEXT        NOT NULL,
                status       TEXT        NOT NULL DEFAULT 'draft',
                mode         TEXT        NOT NULL DEFAULT 'quick',
                config       JSONB       NOT NULL DEFAULT '{}'::jsonb,
                current_step TEXT,
                published_at TIMESTAMPTZ,
                created_by   TEXT,
                created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_trpd_tenant ON tenant_referral_program_drafts(tenant_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_trpd_tenant_status ON tenant_referral_program_drafts(tenant_id, status)");

        // Append-only snapshot history, written each time a draft is published.
        DB::statement("
            CREATE TABLE IF NOT EXISTS tenant_referral_program_versions (
                id           TEXT        PRIMARY KEY DEFAULT gen_random_uuid()::TEXT,
                tenant_id    TEXT        NOT NULL,
                draft_id     TEXT        NOT NULL,
                config       JSONB       NOT NULL DEFAULT '{}'::jsonb,
                published_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by   TEXT,
                created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_trpv_tenant ON tenant_referral_program_versions(tenant_id)");

        // Static, non-tenant-editable industry templates used by the Quick Setup
        // recommendation engine. Seeded below; id is a stable slug, not a UUID.
        DB::statement("
            CREATE TABLE IF NOT EXISTS tenant_referral_program_templates (
                id          TEXT        PRIMARY KEY,
                industry_key TEXT       NOT NULL UNIQUE,
                name        TEXT        NOT NULL,
                description TEXT,
                config      JSONB       NOT NULL DEFAULT '{}'::jsonb,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");

        $templates = [
            'default' => [
                'name' => 'Referrer Program (General)',
                'description' => 'A general-purpose setup where Referrers introduce new business and earn rewards when deals close.',
                'program_type' => 'referrer_program',
            ],
            'saas_software' => [
                'name' => 'SaaS Affiliate Program',
                'description' => 'Affiliates share a link, prospects sign up and convert, commission is earned on paid conversions.',
                'program_type' => 'affiliate',
            ],
            'ecommerce' => [
                'name' => 'Ecommerce Affiliate Program',
                'description' => 'Affiliates drive traffic to product pages and earn a percentage of completed sales.',
                'program_type' => 'affiliate',
            ],
            'retail' => [
                'name' => 'Customer Referral Program',
                'description' => 'Existing customers refer friends and earn a reward once the referral makes a purchase.',
                'program_type' => 'customer_referral',
            ],
            'ngo_nonprofit' => [
                'name' => 'Donor & Volunteer Referral Program',
                'description' => 'Supporters refer new donors or volunteers and the organization tracks and thanks them.',
                'program_type' => 'donor_volunteer',
            ],
            'b2b_services' => [
                'name' => 'Partner Co-Sell Program',
                'description' => 'Partners introduce and co-sell into their networks, splitting commission with your team.',
                'program_type' => 'partner_cosell',
            ],
            'hospitality_travel' => [
                'name' => 'Service Booking Referral Program',
                'description' => 'Referrers send clients who book a service, and earn a reward once the service is completed.',
                'program_type' => 'service_booking',
            ],
        ];

        foreach ($templates as $industryKey => $tpl) {
            $config = json_encode(['program_type' => $tpl['program_type']]);
            DB::statement("
                INSERT INTO tenant_referral_program_templates (id, industry_key, name, description, config)
                VALUES (?, ?, ?, ?, ?::jsonb)
                ON CONFLICT (id) DO NOTHING
            ", [$industryKey, $industryKey, $tpl['name'], $tpl['description'], $config]);
        }
    }

    public function down(): void {}
};
