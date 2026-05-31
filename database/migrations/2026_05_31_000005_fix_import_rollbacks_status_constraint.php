<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Original migration_v8.sql constraint only allowed: pending,running,completed,failed,partial
            // Code also writes: processing (markProcessing), completed_with_warnings (markCompleted), cancelled (guard check)
            DB::statement('ALTER TABLE import_rollbacks DROP CONSTRAINT IF EXISTS import_rollbacks_status_check');
            DB::statement("ALTER TABLE import_rollbacks ADD CONSTRAINT import_rollbacks_status_check
                CHECK (status IN ('pending','running','processing','completed','completed_with_warnings','failed','partial','cancelled'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // NOTE: down() intentionally mirrors up(). Rolling back to the original 5-value
            // constraint would break any live DB that already has rows with processing/
            // completed_with_warnings/cancelled written by cycle-23+ code.
            DB::statement('ALTER TABLE import_rollbacks DROP CONSTRAINT IF EXISTS import_rollbacks_status_check');
            DB::statement("ALTER TABLE import_rollbacks ADD CONSTRAINT import_rollbacks_status_check
                CHECK (status IN ('pending','running','processing','completed','completed_with_warnings','failed','partial','cancelled'))");
        }
    }
};
