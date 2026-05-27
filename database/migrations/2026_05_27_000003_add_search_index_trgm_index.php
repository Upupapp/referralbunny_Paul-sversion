<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // CONCURRENTLY cannot run inside a transaction block
    public $withinTransaction = false;

    public function up(): void
    {
        // pg_trgm was already enabled by 2026_05_26_000001_add_extension_search_trgm_index
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // GIN trigram index on searchable_text so ILIKE queries use an index scan
        // instead of a sequential scan on the full search_index table.
        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_search_index_searchable_trgm
            ON search_index
            USING gin (searchable_text gin_trgm_ops)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_search_index_searchable_trgm');
    }
};
