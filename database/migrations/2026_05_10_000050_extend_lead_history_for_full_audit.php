<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lead_history')) {
            return;
        }

        Schema::table('lead_history', function (Blueprint $table) {
            // Tenant scoping — critical for isolation queries
            if (!Schema::hasColumn('lead_history', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('lead_id');
            }

            // Actor attribution (snapshot — survives user renames/deletes)
            if (!Schema::hasColumn('lead_history', 'actor_name')) {
                $table->string('actor_name')->nullable()->after('reseller');
            }
            if (!Schema::hasColumn('lead_history', 'actor_role')) {
                $table->string('actor_role')->nullable()->after('actor_name');
            }

            // Before/after value capture for financial, partner, stage events
            if (!Schema::hasColumn('lead_history', 'old_values')) {
                $table->jsonb('old_values')->nullable()->after('actor_role');
            }
            if (!Schema::hasColumn('lead_history', 'new_values')) {
                $table->jsonb('new_values')->nullable()->after('old_values');
            }

            // Flexible metadata bag
            if (!Schema::hasColumn('lead_history', 'metadata')) {
                $table->jsonb('metadata')->nullable()->after('new_values');
            }

            // Semantic category (partner, financial, stage, assignment, commission, note, import, system)
            if (!Schema::hasColumn('lead_history', 'category')) {
                $table->string('category')->nullable()->after('type');
            }
        });

        // Performance indexes — wrapped individually so partial failures don't abort everything
        try {
            Schema::table('lead_history', function (Blueprint $table) {
                $table->index(['lead_id', 'created_at'], 'idx_lh_lead_created');
            });
        } catch (\Throwable) {}

        try {
            Schema::table('lead_history', function (Blueprint $table) {
                $table->index(['tenant_id', 'created_at'], 'idx_lh_tenant_created');
            });
        } catch (\Throwable) {}

        try {
            Schema::table('lead_history', function (Blueprint $table) {
                $table->index(['tenant_id', 'category'], 'idx_lh_tenant_category');
            });
        } catch (\Throwable) {}
    }

    public function down(): void
    {
        if (!Schema::hasTable('lead_history')) {
            return;
        }

        foreach (['idx_lh_lead_created', 'idx_lh_tenant_created', 'idx_lh_tenant_category'] as $idx) {
            try {
                Schema::table('lead_history', fn (Blueprint $t) => $t->dropIndex($idx));
            } catch (\Throwable) {}
        }

        Schema::table('lead_history', function (Blueprint $table) {
            foreach (['tenant_id', 'actor_name', 'actor_role', 'old_values', 'new_values', 'metadata', 'category'] as $col) {
                if (Schema::hasColumn('lead_history', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
