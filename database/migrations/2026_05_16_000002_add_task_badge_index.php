<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // Nav task badge: WHERE tenant_id=? AND status NOT IN(...) AND created_at > ?
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_tasks_tenant_status_created
            ON tasks(tenant_id, status, created_at)
            WHERE deleted_at IS NULL
        ");

        // CriticalActionService: newReferrerSignups — WHERE tenant_id=? AND status='invited' AND created_at > ?
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_resellers_tenant_status_created
            ON resellers(tenant_id, status, created_at)
        ");

        // CriticalActionService: newPartnersOnDeals — WHERE tenant_id=? AND created_at > ?
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_deal_partner_splits_tenant_created
            ON deal_partner_splits(tenant_id, created_at)
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void {}
};
