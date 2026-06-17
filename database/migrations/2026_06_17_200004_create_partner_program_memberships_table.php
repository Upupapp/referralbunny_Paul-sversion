<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_program_memberships', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->uuid('partner_id');
            $table->string('program_group_id')->nullable();
            // invited | applied | needs_info | approved | active | paused | suspended | removed | expired | contract_pending | terms_pending
            $table->string('status')->default('active');
            // invite | direct | import | migration | api
            $table->string('source')->default('direct');
            $table->string('invitation_id')->nullable();
            $table->string('active_contract_id')->nullable();
            $table->string('assigned_manager_id')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('partner_id')->references('id')->on('partner_users')->onDelete('cascade');
            $table->foreign('program_group_id')->references('id')->on('program_groups')->nullOnDelete();

            $table->unique(['program_id', 'partner_id'], 'partner_memberships_program_partner_unique');

            $table->index(['tenant_id', 'program_id'], 'ppm_tenant_program');
            $table->index(['tenant_id', 'program_id', 'status'], 'ppm_tenant_program_status');
            $table->index(['partner_id', 'status'], 'ppm_partner_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_program_memberships');
    }
};
