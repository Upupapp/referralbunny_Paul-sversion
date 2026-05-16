<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // partner auth + CriticalActionService use LOWER(partner_email) = ?
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_deal_partner_splits_lower_email
            ON deal_partner_splits (tenant_id, LOWER(partner_email))
        ");

        // TenantRoleService + ContactRoleInviteWebController + ResellerDealController
        // use LOWER(email) = ? on tenant_users
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_tenant_users_lower_email
            ON tenant_users (LOWER(email))
        ");

        // GenericDealImportService + ContactsImportService use LOWER(name) = ? on organizations
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_organizations_lower_name
            ON organizations (tenant_id, LOWER(name))
        ");
    }

    public function down(): void {}
};
