<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // Composite index for the nav task badge query:
        //   WHERE tenant_id = ? AND deleted_at IS NULL AND status NOT IN (...)
        //   AND created_at > ? (last-seen timestamp)
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_tasks_tenant_status_created
            ON tasks(tenant_id, status, created_at)
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void {}
};
