<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * On Supabase/PostgreSQL the import_batches counter columns may be nullable
 * because _000002 added unknown_referrer_rows with ->nullable() and the original
 * table was created before _000006 enforced NOT NULL DEFAULT 0 on fresh DBs.
 *
 * This migration is a no-op on fresh SQLite/CI environments (columns are already
 * NOT NULL from _000006). On PostgreSQL it backfills NULLs and sets NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        if (!Schema::hasTable('import_batches')) return;

        $columns = ['successful_rows', 'failed_rows', 'unknown_referrer_rows'];

        foreach ($columns as $col) {
            if (!Schema::hasColumn('import_batches', $col)) continue;
            try {
                DB::statement("UPDATE import_batches SET {$col} = 0 WHERE {$col} IS NULL");
                DB::statement("ALTER TABLE import_batches ALTER COLUMN {$col} SET NOT NULL");
                DB::statement("ALTER TABLE import_batches ALTER COLUMN {$col} SET DEFAULT 0");
            } catch (\Throwable) {}
        }
    }

    public function down(): void {}
};
