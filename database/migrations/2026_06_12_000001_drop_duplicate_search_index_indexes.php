<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // idx_search_index_type_entity duplicates the UNIQUE (entity_type, entity_id)
        // constraint index search_index_entity_type_entity_id_key (both 392kB, 0 scans) —
        // the constraint index stays and keeps enforcing uniqueness.
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_search_index_type_entity');

        // search_index_entity_type_idx duplicates idx_search_index_entity_type
        // (both btree(entity_type)), but only the latter is used by the planner
        // (347 scans vs 0) — drop the unused one.
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS search_index_entity_type_idx');
    }

    public function down(): void
    {
        DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS idx_search_index_type_entity ON search_index (entity_type, entity_id)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS search_index_entity_type_idx ON search_index (entity_type)');
    }
};
