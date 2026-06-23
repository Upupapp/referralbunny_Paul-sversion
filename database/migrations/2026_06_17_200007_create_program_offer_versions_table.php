<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_offer_versions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('offer_id');
            $table->unsignedInteger('version_number')->default(1);
            // draft | published | superseded
            $table->string('status')->default('draft');
            $table->string('currency', 3)->default('USD');
            // fixed | percentage | recurring | tiered | milestone | two_sided | non_monetary
            $table->string('reward_model')->default('percentage');
            // deal_value | deal_closed | stage_reached | referral_submitted | custom
            $table->string('qualifying_event')->default('deal_closed');
            // Normalized financials (most common fields directly accessible, complex rules in reward_rules JSON)
            $table->decimal('fixed_amount', 15, 4)->nullable();
            $table->decimal('percentage_rate', 8, 4)->nullable();
            $table->decimal('cap_amount', 15, 4)->nullable();
            // Full calculation rules including tiers, milestones, splits, holds, clawbacks
            $table->json('reward_rules')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->string('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            // immutable copy of this version's full config for historical reproducibility
            $table->json('immutable_snapshot')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('offer_id')->references('id')->on('program_offers')->onDelete('cascade');

            $table->unique(['offer_id', 'version_number'], 'pov_offer_version_unique');
            $table->index(['program_id', 'offer_id'], 'pov_program_offer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_offer_versions');
    }
};
