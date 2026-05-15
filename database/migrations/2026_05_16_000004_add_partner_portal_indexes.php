<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // PartnerPortalController::authorizedDealIds — WHERE partner_user_id=? AND tenant_id=? AND status='active'
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_deal_partners_user_tenant_status
            ON deal_partners(partner_user_id, tenant_id, status)
        ");

        // PartnerPortalController::commissions + authorizedDealIds raw query — WHERE partner_user_id=? AND tenant_id=?
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_deal_partner_splits_user_tenant
            ON deal_partner_splits(partner_user_id, tenant_id)
            WHERE deleted_at IS NULL
        ");

        // CriticalActionService::forPartner — WHERE partner_id=? AND tenant_id=? AND partner_unread > 0
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_partner_threads_partner_tenant
            ON partner_threads(partner_id, tenant_id)
        ");
    }

    public function down(): void {}
};
