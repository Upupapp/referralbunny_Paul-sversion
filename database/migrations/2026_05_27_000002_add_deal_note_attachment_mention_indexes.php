<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Speeds up WITH(['attachments']) eager load: WHERE deal_comment_id IN (...)
        DB::unprepared('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_dna_comment_tenant
            ON deal_note_attachments (deal_comment_id, tenant_id)');

        // Speeds up WITH(['mentions']) eager load: WHERE deal_comment_id IN (...)
        DB::unprepared('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_dnm_comment_tenant
            ON deal_note_mentions (deal_comment_id, tenant_id)');
    }

    public function down(): void
    {
        DB::unprepared('DROP INDEX CONCURRENTLY IF EXISTS idx_dna_comment_tenant');
        DB::unprepared('DROP INDEX CONCURRENTLY IF EXISTS idx_dnm_comment_tenant');
    }
};
