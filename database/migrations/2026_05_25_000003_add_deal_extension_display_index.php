<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE INDEX IF NOT EXISTS idx_daer_deal_tenant_status
             ON deal_assignment_extension_requests (deal_id, tenant_id, status)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_daer_deal_tenant_status');
    }
};
