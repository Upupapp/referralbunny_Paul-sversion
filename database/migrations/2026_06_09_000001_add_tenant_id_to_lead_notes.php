<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lead_notes', 'tenant_id')) {
            return;
        }

        Schema::table('lead_notes', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('lead_id');
        });

        // Backfill from leads table — any orphaned notes (lead deleted) stay null
        DB::statement('
            UPDATE lead_notes ln
            SET    tenant_id = l.tenant_id
            FROM   leads l
            WHERE  ln.lead_id = l.id
              AND  ln.tenant_id IS NULL
        ');

        Schema::table('lead_notes', function (Blueprint $table) {
            $table->index('tenant_id', 'idx_lead_notes_tenant_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('lead_notes', 'tenant_id')) {
            return;
        }
        // PostgreSQL drops associated indexes automatically when the column is dropped.
        Schema::table('lead_notes', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
