<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Remove duplicate email_key rows that existed before EmailLogger gained its
        // universal pre-insert dedup check (commit 4216763). Use ctid (PostgreSQL
        // physical row identifier) to keep exactly one row per key regardless of
        // created_at ties, then create the UNIQUE index.
        DB::statement("
            DELETE FROM email_logs
            WHERE ctid NOT IN (
                SELECT MIN(ctid)
                FROM email_logs
                GROUP BY email_key
            )
        ");

        // UNIQUE index makes EmailLogger dedup a DB-level guarantee, not just
        // application logic. Concurrent double-inserts will raise a unique violation
        // that EmailLogger's pre-insert check catches on the next attempt.
        DB::statement('
            CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS email_logs_email_key_unique
            ON email_logs (email_key)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS email_logs_email_key_unique');
    }
};
