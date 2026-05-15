<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // commission_splits queries use LOWER(reseller_name) = ? for referrer scoping.
        // The plain B-tree index on (lead_id, reseller_name) is NOT used by PostgreSQL
        // for LOWER() equality queries — a functional index is required.
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_commission_splits_lower_reseller
            ON commission_splits (lead_id, LOWER(reseller_name))
        ");

        // resellers.name lookups use case-insensitive comparison in CriticalActionService
        // and TenantAdminController when joining reseller_name back to a reseller record.
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_resellers_lower_name
            ON resellers (tenant_id, LOWER(name))
        ");
    }

    public function down(): void {}
};
