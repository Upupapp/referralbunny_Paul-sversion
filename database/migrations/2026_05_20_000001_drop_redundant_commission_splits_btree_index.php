<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // idx_commission_splits_lead_reseller (plain B-tree on lead_id, reseller_name) is redundant.
        // All portal queries use LOWER(reseller_name) and are served by the functional index
        // idx_commission_splits_lower_reseller added in migration 2026_05_16_000009.
        // The plain index adds write overhead on every reassignment with no read benefit.
        DB::statement('DROP INDEX IF EXISTS idx_commission_splits_lead_reseller');
    }

    public function down(): void
    {
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_commission_splits_lead_reseller
            ON commission_splits(lead_id, reseller_name)
        ');
    }
};
