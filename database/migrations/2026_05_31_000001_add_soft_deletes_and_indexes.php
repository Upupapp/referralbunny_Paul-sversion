<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. SoftDeletes on invoices
        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'deleted_at')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 2. SoftDeletes on subscriptions
        if (Schema::hasTable('subscriptions') && !Schema::hasColumn('subscriptions', 'deleted_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 3. SoftDeletes on contacts
        if (Schema::hasTable('contacts') && !Schema::hasColumn('contacts', 'deleted_at')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 4. Partial unique index — one active subscription per tenant
        // Prevents race conditions from creating two active subscriptions for the same tenant
        if (Schema::hasTable('subscriptions')) {
            DB::statement("
                CREATE UNIQUE INDEX IF NOT EXISTS idx_subscriptions_one_active_per_tenant
                ON subscriptions (tenant_id)
                WHERE status IN ('active', 'trial', 'comped', 'internal', 'manual')
                AND deleted_at IS NULL
            ");
        }

        // 5. Composite index on search_index (tenant_id, is_deleted) for tenant-scoped searches
        if (Schema::hasTable('search_index')) {
            DB::statement("
                CREATE INDEX IF NOT EXISTS idx_search_index_tenant_active
                ON search_index (tenant_id, is_deleted)
                WHERE tenant_id IS NOT NULL AND is_deleted = FALSE
            ");
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_subscriptions_one_active_per_tenant');
        DB::statement('DROP INDEX IF EXISTS idx_search_index_tenant_active');

        if (Schema::hasColumn('invoices', 'deleted_at')) {
            Schema::table('invoices', fn(Blueprint $t) => $t->dropSoftDeletes());
        }
        if (Schema::hasColumn('subscriptions', 'deleted_at')) {
            Schema::table('subscriptions', fn(Blueprint $t) => $t->dropSoftDeletes());
        }
        if (Schema::hasColumn('contacts', 'deleted_at')) {
            Schema::table('contacts', fn(Blueprint $t) => $t->dropSoftDeletes());
        }
    }
};
