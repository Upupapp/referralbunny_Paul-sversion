<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // markAllAdminRead() UPDATEs partner_threads WHERE tenant_id AND admin_unread > 0.
        // Nav badge query also filters partner_threads for admin_unread > 0 per tenant.
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_partner_threads_tenant_admin_unread
            ON partner_threads (tenant_id, admin_unread)
            WHERE admin_unread > 0
        ");
    }

    public function down(): void {}
};
