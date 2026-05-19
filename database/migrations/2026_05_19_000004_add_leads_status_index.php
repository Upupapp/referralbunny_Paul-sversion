<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        try {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_status ON leads(status) WHERE deleted_at IS NULL');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('idx_leads_status migration failed', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        try {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_leads_status');
        } catch (\Throwable) {}
    }
};
