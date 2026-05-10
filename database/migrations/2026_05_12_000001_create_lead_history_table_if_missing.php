<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the lead_history table if it does not already exist.
 * Safe to run even if the table was created in Supabase or a prior migration.
 * Uses json (not jsonb) for cross-DB compatibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_history')) {
            return; // Table already exists — the extension migration handles column additions
        }

        try {
            Schema::create('lead_history', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('lead_id');
                $table->string('tenant_id')->nullable();

                $table->string('action');
                $table->string('type')->nullable();
                $table->string('category')->nullable();
                $table->string('reseller')->nullable();
                $table->string('actor_name')->nullable();
                $table->string('actor_role')->nullable();

                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('metadata')->nullable();

                $table->date('date')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        } catch (\Throwable) {
            return; // Table creation failed — may already exist via race condition
        }

        // Indexes — each wrapped individually so partial failure doesn't abort
        foreach ([
            ['lead_id', 'created_at'],
            ['tenant_id', 'created_at'],
            ['tenant_id', 'category'],
        ] as $cols) {
            try {
                Schema::table('lead_history', fn (Blueprint $t) => $t->index($cols));
            } catch (\Throwable) {}
        }
    }

    public function down(): void {} // Never drop history
};
