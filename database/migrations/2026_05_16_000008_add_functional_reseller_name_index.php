<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // All referrer-scoped lead queries use LOWER(reseller_name) = ?
        // A plain B-tree index on reseller_name is NOT used by this pattern in PostgreSQL.
        // A functional index is required.
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_leads_tenant_lower_reseller_name
            ON leads (tenant_id, LOWER(reseller_name))
            WHERE deleted_at IS NULL
        ");

        // resellers.email lookups use LOWER(email) in auth flows
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_resellers_lower_email
            ON resellers (tenant_id, LOWER(email))
        ");

        // partner_users.email lookups in auth + commission service
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_partner_users_lower_email
            ON partner_users (tenant_id, LOWER(email))
        ");
    }

    public function down(): void {}
};
