<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrer_program_memberships', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->uuid('reseller_id');
            $table->string('program_group_id')->nullable();
            // invited | applied | needs_info | approved | active | paused | suspended | removed | expired | contract_pending | terms_pending
            $table->string('status')->default('active');
            // invite | application | direct | import | migration | api
            $table->string('source')->default('direct');
            $table->string('invitation_id')->nullable();
            $table->string('application_id')->nullable();
            $table->string('active_contract_id')->nullable();
            $table->string('assigned_manager_id')->nullable();
            $table->string('referral_code')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('reseller_id')->references('id')->on('resellers')->onDelete('cascade');
            $table->foreign('program_group_id')->references('id')->on('program_groups')->nullOnDelete();

            // one active membership per reseller per program
            $table->unique(['program_id', 'reseller_id'], 'referrer_memberships_program_reseller_unique');
            $table->unique(['program_id', 'referral_code'], 'referrer_memberships_program_code_unique');

            $table->index(['tenant_id', 'program_id'], 'rpm_tenant_program');
            $table->index(['tenant_id', 'program_id', 'status'], 'rpm_tenant_program_status');
            $table->index(['program_id', 'program_group_id'], 'rpm_program_group');
            $table->index(['reseller_id', 'status'], 'rpm_reseller_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrer_program_memberships');
    }
};
