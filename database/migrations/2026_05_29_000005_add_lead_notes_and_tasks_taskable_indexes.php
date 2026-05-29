<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // CONCURRENTLY cannot run inside a transaction
    public bool $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_lead_notes_lead_id ON lead_notes (lead_id)');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tasks_taskable ON tasks (tenant_id, taskable_type, taskable_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_lead_notes_lead_id');
        DB::statement('DROP INDEX IF EXISTS idx_tasks_taskable');
    }
};
