<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support ALTER TABLE DROP COLUMN IF EXISTS.
        // On SQLite the email_logs table is always freshly created by migration
        // 000005 with the correct varchar column, so no fixup is needed.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // PostgreSQL: tenant_id was incorrectly typed as uuid; tenant IDs are
        // slug strings like 'lgu-ids'. Drop and re-add as varchar(36).
        DB::statement('ALTER TABLE email_logs DROP COLUMN IF EXISTS tenant_id');
        DB::statement('ALTER TABLE email_logs ADD COLUMN tenant_id varchar(36)');
        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_tenant_id_idx ON email_logs(tenant_id)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE email_logs DROP COLUMN IF EXISTS tenant_id');
        DB::statement('ALTER TABLE email_logs ADD COLUMN tenant_id uuid');
    }
};
