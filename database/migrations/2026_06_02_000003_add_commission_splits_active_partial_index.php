<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // All Eloquent queries on commission_splits now include WHERE deleted_at IS NULL
        // (added automatically by the SoftDeletes global scope). A partial index on
        // lead_id WHERE active covers the hot read path in ResellerDealController::show().
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_commission_splits_active
            ON commission_splits (lead_id)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_commission_splits_active');
    }
};
