<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Partial composite index for pending tasks queries (dashboard widget + digest job)
        // Covers: WHERE tenant_id = ? AND status IN (...) AND deleted_at IS NULL ORDER BY due_at
        try {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tasks_tenant_status_due_pending
                ON tasks(tenant_id, status, due_at)
                WHERE deleted_at IS NULL');
        } catch (\Throwable) {}
    }

    public function down(): void
    {
        try {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_tasks_tenant_status_due_pending');
        } catch (\Throwable) {}
    }
};
