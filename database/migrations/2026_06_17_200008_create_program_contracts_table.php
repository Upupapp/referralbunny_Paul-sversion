<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_contracts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            // referrer | partner
            $table->string('membership_type');
            $table->string('membership_id');
            $table->string('offer_version_id')->nullable();
            $table->string('program_terms_version_id')->nullable();
            $table->string('group_terms_version_id')->nullable();
            $table->string('individual_override_version_id')->nullable();
            // proposed | active | declined | superseded | ended | expired
            $table->string('status')->default('proposed');
            $table->timestamp('proposed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('acceptance_ip', 45)->nullable();
            $table->text('acceptance_user_agent')->nullable();
            // click | api | import
            $table->string('acceptance_method')->nullable();
            $table->json('acceptance_evidence')->nullable();
            $table->string('previous_contract_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');

            $table->index(['program_id', 'membership_type', 'membership_id'], 'pc_program_membership');
            $table->index(['program_id', 'status'], 'pc_program_status');
            $table->index(['membership_type', 'membership_id', 'status'], 'pc_member_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_contracts');
    }
};
