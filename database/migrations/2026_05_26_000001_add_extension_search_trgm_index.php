<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // CONCURRENTLY cannot run inside a transaction block
    public bool $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // GIN trigram index for ILIKE search on batch reference and reason fields.
        // Supports the getBatchesPaginated() search path without seq-scanning the table.
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_derb_trgm_search
            ON deal_extension_request_batches
            USING gin (batch_reference gin_trgm_ops, shared_reason gin_trgm_ops)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_derb_trgm_search');
    }
};
