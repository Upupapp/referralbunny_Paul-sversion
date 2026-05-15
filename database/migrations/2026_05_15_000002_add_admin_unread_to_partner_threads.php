<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE partner_threads ADD COLUMN IF NOT EXISTS admin_unread INT NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE partner_threads ADD COLUMN IF NOT EXISTS thread_type TEXT NOT NULL DEFAULT 'deal'");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_partner_threads_type ON partner_threads(thread_type)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_partner_threads_admin_unread ON partner_threads(tenant_id, admin_unread)");
    }

    public function down(): void {}
};
