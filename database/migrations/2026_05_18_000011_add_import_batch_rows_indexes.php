<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Primary access pattern: fetch + order rows for a batch
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_import_batch_rows_batch_row_number
            ON import_batch_rows (import_batch_id, row_number)
        ');

        // Filter by validation_status (preview groupBy, generateFailedRowsCsv)
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_import_batch_rows_batch_validation_status
            ON import_batch_rows (import_batch_id, validation_status)
        ');

        // Partial index for rows with execution errors (generateFailedRowsCsv orWhereNotNull)
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_import_batch_rows_batch_has_error
            ON import_batch_rows (import_batch_id)
            WHERE error_message IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_import_batch_rows_batch_row_number');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_import_batch_rows_batch_validation_status');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_import_batch_rows_batch_has_error');
    }
};
