<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_action_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            // referrer | partner
            $table->string('membership_type');
            $table->string('membership_id');
            // accept_terms | review_contract | upload_document | complete_profile
            // resolve_application | acknowledge_program_ending
            $table->string('action_type');
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            // pending | completed | dismissed | expired
            $table->string('status')->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('source_configuration_version_id')->nullable();
            // not_sent | queued | sent | failed
            $table->string('notification_status')->default('not_sent');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');

            $table->index(['program_id', 'membership_type', 'membership_id'], 'mai_program_membership');
            $table->index(['program_id', 'status'], 'mai_program_status');
            $table->index(['membership_type', 'membership_id', 'status'], 'mai_member_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_action_items');
    }
};
