<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the plain btree index (if created by a prior migration) so we can
        // replace it with a functional index that supports LOWER(reseller_name) lookups.
        DB::statement('DROP INDEX IF EXISTS idx_leads_tenant_reseller_name');
        DB::statement("CREATE INDEX IF NOT EXISTS idx_leads_tenant_reseller_name ON leads(tenant_id, LOWER(reseller_name)) WHERE deleted_at IS NULL AND reseller_name IS NOT NULL AND reseller_name <> ''");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_leads_tenant_reseller_name');
        DB::statement("CREATE INDEX IF NOT EXISTS idx_leads_tenant_reseller_name ON leads(tenant_id, reseller_name) WHERE deleted_at IS NULL AND reseller_name IS NOT NULL AND reseller_name <> ''");
    }
};
