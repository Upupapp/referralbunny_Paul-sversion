<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_configuration_versions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->unsignedInteger('version_number')->default(1);
            // draft | published | superseded | reverted
            $table->string('status')->default('draft');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('published_by')->nullable();
            // immutable snapshot of the program configuration at publish time
            $table->json('snapshot')->nullable();
            $table->text('change_summary')->nullable();
            $table->text('portal_impact_summary')->nullable();
            $table->string('previous_version_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');

            $table->unique(['program_id', 'version_number'], 'pcv_program_version_unique');
            $table->index(['program_id', 'status'], 'pcv_program_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_configuration_versions');
    }
};
