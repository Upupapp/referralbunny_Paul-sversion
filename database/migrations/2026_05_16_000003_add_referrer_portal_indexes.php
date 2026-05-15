<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // ResellerPortalController::activityLog — WHERE tenant_id=? AND (entity='reseller' AND entity_id=? OR entity='lead' AND entity_id IN(...))
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_activity_logs_tenant_entity
            ON activity_logs(tenant_id, entity, entity_id)
        ");

        // ResellerDealController::show + activityLog — WHERE lead_id=? AND LOWER(reseller_name)=?
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_commission_splits_lead_reseller
            ON commission_splits(lead_id, reseller_name)
        ");

        // ResellerPortalController::dashboard + ResellerDealController::show — WHERE deal_id=?
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_deal_partner_splits_deal_id
            ON deal_partner_splits(deal_id)
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void {}
};
