<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // buildSummary queries import_snapshots filtered by import_batch_id, rollback_status='pending',
        // then groups by entity_type. A partial index on (import_batch_id, entity_type) WHERE pending
        // covers both the filter and the groupBy with a narrow, fast-decaying partial key set.
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_import_snapshots_batch_type_pending
            ON import_snapshots (import_batch_id, entity_type)
            WHERE rollback_status = \'pending\'
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_import_snapshots_batch_type_pending');
    }
};
