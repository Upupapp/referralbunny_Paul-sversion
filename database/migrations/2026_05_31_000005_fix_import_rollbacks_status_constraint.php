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
            // Restores the same extended constraint as up() — safe for live DBs where cycle-23+
            // code may still be writing processing/completed_with_warnings/cancelled values.
            DB::statement('ALTER TABLE import_rollbacks DROP CONSTRAINT IF EXISTS import_rollbacks_status_check');
            DB::statement("ALTER TABLE import_rollbacks ADD CONSTRAINT import_rollbacks_status_check
                CHECK (status IN ('pending','running','processing','completed','completed_with_warnings','failed','partial','cancelled'))");
        }
    }
};
