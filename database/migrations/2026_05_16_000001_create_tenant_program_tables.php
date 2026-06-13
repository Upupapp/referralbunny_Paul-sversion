<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::create('tenant_pipeline_stages', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('stage_key');
                $table->string('name');
                $table->integer('position')->default(0);
                $table->integer('days_limit')->nullable();
                $table->string('color')->default('#9CA3AF');
                $table->boolean('is_final')->default(false);
                $table->boolean('is_won')->default(false);
                $table->timestamps();

                $table->unique(['tenant_id', 'stage_key']);
                $table->index('tenant_id', 'idx_tps_tenant');
                $table->index(['tenant_id', 'position'], 'idx_tps_tenant_pos');
            });

            Schema::create('tenant_program_configs', function (Blueprint $table) {
                $table->string('tenant_id')->primary();
                $table->string('industry')->nullable();
                $table->json('sub_industries')->default('[]');
                $table->string('lead_label')->default('Deal');
                $table->string('value_label')->default('Deal Value');
                $table->string('commission_type')->default('percentage_of_value');
                $table->decimal('company_share_pct', 5, 2)->default(30);
                $table->decimal('referrer_share_pct', 5, 2)->default(70);
                $table->integer('default_expiry_days')->default(21);
                $table->string('reassignment_mode')->default('manual');
                $table->boolean('onboarding_complete')->default(false);
                $table->string('template_applied')->nullable();
                $table->timestamps();
            });

            return;
        }

        // Generic pipeline stages per tenant (separate from LGU IDS tenant_pipeline_stage_rules)
        DB::statement("
            CREATE TABLE IF NOT EXISTS tenant_pipeline_stages (
                id            TEXT        PRIMARY KEY DEFAULT gen_random_uuid()::TEXT,
                tenant_id     TEXT        NOT NULL,
                stage_key     TEXT        NOT NULL,
                name          TEXT        NOT NULL,
                position      INT         NOT NULL DEFAULT 0,
                days_limit    INT,
                color         TEXT        NOT NULL DEFAULT '#9CA3AF',
                is_final      BOOLEAN     NOT NULL DEFAULT FALSE,
                is_won        BOOLEAN     NOT NULL DEFAULT FALSE,
                created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                UNIQUE (tenant_id, stage_key)
            )
        ");

        DB::statement("CREATE INDEX IF NOT EXISTS idx_tps_tenant ON tenant_pipeline_stages(tenant_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_tps_tenant_pos ON tenant_pipeline_stages(tenant_id, position)");

        // Tenant-level program config: industry, commission structure, labels
        DB::statement("
            CREATE TABLE IF NOT EXISTS tenant_program_configs (
                tenant_id           TEXT        PRIMARY KEY,
                industry            TEXT,
                sub_industries      JSONB       NOT NULL DEFAULT '[]',
                lead_label          TEXT        NOT NULL DEFAULT 'Deal',
                value_label         TEXT        NOT NULL DEFAULT 'Deal Value',
                commission_type     TEXT        NOT NULL DEFAULT 'percentage_of_value',
                company_share_pct   NUMERIC(5,2) NOT NULL DEFAULT 30,
                referrer_share_pct  NUMERIC(5,2) NOT NULL DEFAULT 70,
                default_expiry_days INT         NOT NULL DEFAULT 21,
                reassignment_mode   TEXT        NOT NULL DEFAULT 'manual',
                onboarding_complete BOOLEAN     NOT NULL DEFAULT FALSE,
                template_applied    TEXT,
                created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
    }

    public function down(): void {}
};
