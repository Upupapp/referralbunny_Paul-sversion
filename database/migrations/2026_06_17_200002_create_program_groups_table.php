<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_groups', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            // referrer | partner | both
            $table->string('membership_type')->default('referrer');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            // active | inactive | archived
            $table->string('status')->default('active');
            $table->boolean('is_default')->default(false);
            // invite_only | application | both
            $table->string('application_mode')->default('invite_only');
            $table->string('default_offer_id')->nullable();
            $table->text('portal_welcome_content')->nullable();
            // public | members_only | hidden
            $table->string('visibility')->default('members_only');
            // auto | manual
            $table->string('manager_assignment_mode')->default('manual');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');

            $table->unique(['program_id', 'slug'], 'program_groups_program_slug_unique');
            $table->index(['tenant_id', 'program_id'], 'program_groups_tenant_program');
            $table->index(['program_id', 'status'], 'program_groups_program_status');
            $table->index(['program_id', 'is_default'], 'program_groups_program_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_groups');
    }
};
