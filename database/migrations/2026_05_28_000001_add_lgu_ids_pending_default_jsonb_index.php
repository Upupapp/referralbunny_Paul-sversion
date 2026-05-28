<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_lgu_pending_default
            ON leads ((data->>'amount_defaulted'), (data->>'amount_confirmation_status'))
            WHERE tenant_id = 'lgu-ids' AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_leads_lgu_pending_default');
    }
};
