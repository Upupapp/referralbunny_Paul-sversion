<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // Nav badge query + markAllAdminRead UPDATE both filter message_threads
        // by tenant_id WHERE admin_unread > 0. A partial index speeds both paths.
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_message_threads_tenant_admin_unread
            ON message_threads (tenant_id, admin_unread)
            WHERE admin_unread > 0
        ");
    }

    public function down(): void {}
};
