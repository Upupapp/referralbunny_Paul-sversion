<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // The Supabase-created constraint only allowed 5 type values (assignment, commission,
        // expiry, stage, financial). All other types — note, partner, deal, import, amount,
        // referrer — were silently rejected, causing DealActivityService::record() to fail
        // for every event except stage moves and deal creation.
        try {
            DB::statement('ALTER TABLE lead_history DROP CONSTRAINT IF EXISTS lead_history_type_check');
        } catch (\Throwable $e) {
            // Constraint may not exist in all environments
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE lead_history ADD CONSTRAINT lead_history_type_check
                CHECK (type = ANY (ARRAY['assignment','commission','expiry','stage','financial',
                                        'note','partner','deal','import','amount','referrer',
                                        'approval','archive','system']))");
        } catch (\Throwable) {}
    }
};
