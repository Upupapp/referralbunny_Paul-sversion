<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Reseller calendar: deals filtered by tenant_id + reseller_name
        DB::statement("CREATE INDEX IF NOT EXISTS idx_leads_tenant_reseller_name ON leads(tenant_id, reseller_name)");

        // Prevent duplicate direct threads per partner (partial unique index — PostgreSQL only)
        // Ensures firstOrCreate() is safe even under concurrent requests
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_partner_threads_direct_unique
            ON partner_threads(tenant_id, partner_id)
            WHERE deal_id IS NULL
        ");
    }

    public function down(): void {}
};
