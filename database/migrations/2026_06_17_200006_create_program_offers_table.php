<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_offers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('program_group_id')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            // active | inactive | archived
            $table->string('status')->default('active');
            // public | group_only | hidden
            $table->string('visibility')->default('group_only');
            $table->string('current_version_id')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('program_group_id')->references('id')->on('program_groups')->nullOnDelete();

            $table->unique(['program_id', 'code'], 'program_offers_program_code_unique');
            $table->index(['tenant_id', 'program_id'], 'program_offers_tenant_program');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_offers');
    }
};
