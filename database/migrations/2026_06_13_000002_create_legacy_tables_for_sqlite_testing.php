<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * activity_logs, tenant_custom_fields, and tenant_import_templates exist in
     * production Postgres but predate this project's migration history — there is
     * no Schema::create for them anywhere. Under sqlite (php artisan test), they
     * don't exist at all, which breaks ActivityLog::create() (silently, via its
     * try/catch) and ReferralProgramPublishService::publish() (uncaught, since
     * TenantCustomField/TenantImportTemplate writes are not wrapped). This
     * sqlite-only migration creates them so the test suite has parity.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') return;

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('entity')->nullable();
            $table->string('entity_id')->nullable();
            $table->json('metadata')->default('{}');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'entity', 'entity_id']);
            $table->index(['tenant_id', 'action', 'created_at']);
        });

        Schema::create('tenant_custom_fields', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('destination_type');
            $table->string('field_key');
            $table->string('field_label');
            $table->string('data_type');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_importable')->default(true);
            $table->boolean('is_exportable')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->string('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'destination_type', 'field_key']);
        });

        Schema::create('tenant_import_templates', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('destination_type');
            $table->string('template_name');
            $table->string('template_key')->unique();
            $table->string('industry_key')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->json('fields_json')->nullable();
            $table->json('required_fields_json')->nullable();
            $table->json('aliases_json')->nullable();
            $table->json('sample_headers_json')->nullable();
            $table->string('created_from_import_batch_id')->nullable();
            $table->string('created_by_user_id')->nullable();
            $table->string('approved_by_user_id')->nullable();
            $table->timestamp('double_authenticated_at')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'destination_type']);
        });
    }

    public function down(): void {}
};
