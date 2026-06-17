<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('slug');
            $table->string('internal_code')->nullable();
            $table->text('short_description')->nullable();
            $table->text('full_description')->nullable();
            $table->string('program_type')->default('referral');
            $table->string('objective_type')->nullable();
            $table->string('industry_key')->nullable();
            $table->string('sub_industry_key')->nullable();
            // draft | scheduled | active | paused | ended | archived
            $table->string('status')->default('draft');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('timezone')->default('UTC');
            $table->string('default_currency', 3)->default('USD');
            $table->string('locale', 10)->default('en');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('enrollment_opens_at')->nullable();
            $table->timestamp('enrollment_closes_at')->nullable();
            $table->timestamp('referral_period_opens_at')->nullable();
            $table->timestamp('referral_period_closes_at')->nullable();
            $table->boolean('evergreen')->default(true);
            // private | unlisted | public
            $table->string('public_visibility')->default('private');
            // invite_only | application | both | direct | import | api
            $table->string('application_mode')->default('invite_only');
            // manual | auto
            $table->string('approval_mode')->default('manual');
            // first_touch | last_touch | manual | code | link | deal_registration
            $table->string('attribution_model')->default('last_touch');
            $table->unsignedInteger('attribution_window_days')->default(30);
            $table->unsignedInteger('referral_expiry_days')->nullable();
            // reject | allow | flag
            $table->string('duplicate_referral_policy')->default('reject');
            // one_per_org | allow_multiple
            $table->string('organization_uniqueness_policy')->default('one_per_org');
            // reject | allow | flag
            $table->string('existing_customer_policy')->default('reject');
            // reject | allow
            $table->string('self_referral_policy')->default('reject');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('launched_by')->nullable();
            $table->timestamp('launched_at')->nullable();
            $table->string('paused_by')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->string('paused_reason')->nullable();
            $table->string('ended_by')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('archived_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('current_configuration_version_id')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // slug unique within tenant
            $table->unique(['tenant_id', 'slug'], 'programs_tenant_slug_unique');
            // internal_code unique within tenant (enforced by partial check in application layer)
            $table->index(['tenant_id', 'status'], 'programs_tenant_status');
            $table->index(['tenant_id', 'is_default'], 'programs_tenant_default');
            $table->index(['tenant_id', 'created_at'], 'programs_tenant_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
