<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // The leads_status_check constraint only allows active/expiring/expired/reassigned/declined.
        // 'archived' was missing, causing the archive approval to silently fail with SQLSTATE 23514.
        try {
            DB::statement('ALTER TABLE leads DROP CONSTRAINT IF EXISTS leads_status_check');
        } catch (\Throwable) {}

        try {
            DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_status_check
                CHECK (status = ANY (ARRAY[
                    'active'::text, 'expiring'::text, 'expired'::text,
                    'reassigned'::text, 'declined'::text, 'archived'::text
                ]))");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('leads_status_check migration failed', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE leads DROP CONSTRAINT IF EXISTS leads_status_check');
            DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_status_check
                CHECK (status = ANY (ARRAY[
                    'active'::text, 'expiring'::text, 'expired'::text,
                    'reassigned'::text, 'declined'::text
                ]))");
        } catch (\Throwable) {}
    }
};
