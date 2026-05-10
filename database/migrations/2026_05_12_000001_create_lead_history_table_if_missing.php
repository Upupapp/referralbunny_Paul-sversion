<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the lead_history table if it does not already exist.
 * Safe to run even if the table was created in Supabase or a prior migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_history')) {
            return; // Table already exists — the extension migration handles columns
        }

        Schema::create('lead_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->index();
            $table->string('tenant_id')->nullable()->index();

            // Core event fields
            $table->string('action');
            $table->string('type')->nullable();
            $table->string('category')->nullable();

            // Actor attribution
            $table->string('reseller')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();

            // Before/after value capture
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->jsonb('metadata')->nullable();

            // Date of the activity (business date, not system timestamp)
            $table->date('date')->nullable();

            $table->timestamp('created_at')->useCurrent();
            // No updated_at — history is immutable

            $table->index(['lead_id', 'created_at'], 'idx_lh_lead_created');
            $table->index(['tenant_id', 'created_at'], 'idx_lh_tenant_created');
            $table->index(['tenant_id', 'category'], 'idx_lh_tenant_category');
        });
    }

    public function down(): void
    {
        // Intentionally left empty — never drop history in a down migration
    }
};
