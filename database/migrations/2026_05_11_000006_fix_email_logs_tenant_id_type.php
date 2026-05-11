<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // tenant_id was incorrectly created as uuid; tenant IDs in this app
        // are slug strings like 'lgu-ids', not UUIDs.
        // Drop and re-add the column as varchar so inserts don't fail.
        DB::statement('ALTER TABLE email_logs DROP COLUMN IF EXISTS tenant_id');
        DB::statement('ALTER TABLE email_logs ADD COLUMN tenant_id varchar(36)');
        DB::statement('CREATE INDEX IF NOT EXISTS email_logs_tenant_id_idx ON email_logs(tenant_id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE email_logs DROP COLUMN IF EXISTS tenant_id');
        DB::statement('ALTER TABLE email_logs ADD COLUMN tenant_id uuid');
    }
};
