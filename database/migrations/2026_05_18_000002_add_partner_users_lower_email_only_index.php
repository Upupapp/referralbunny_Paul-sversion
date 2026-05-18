<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The composite (tenant_id, LOWER(email)) index exists but cannot be used
        // when tenant_id is absent from the WHERE clause (e.g. password reset lookup).
        // This single-column index covers that case.
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_partner_users_lower_email_only ON partner_users (LOWER(email))');
        } catch (\Throwable) {}
    }

    public function down(): void
    {
        try {
            DB::statement('DROP INDEX IF EXISTS idx_partner_users_lower_email_only');
        } catch (\Throwable) {}
    }
};
