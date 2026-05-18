<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Remove duplicate email_key rows that existed before EmailLogger gained its
        // universal pre-insert dedup check (commit 4216763). Keep the most recent
        // record per key; drop older duplicates so the UNIQUE index can be created.
        DB::statement("
            DELETE FROM email_logs a
            USING (
                SELECT email_key, MAX(created_at) AS latest
                FROM email_logs
                GROUP BY email_key
                HAVING COUNT(*) > 1
            ) b
            WHERE a.email_key = b.email_key
              AND a.created_at < b.latest
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
