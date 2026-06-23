<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add nullable program_id to leads (Deals). Nullable so existing records
        // are unaffected; DefaultProgramMigrationService backfills on first activation.
        Schema::table('leads', function (Blueprint $table) {
            $table->string('program_id')->nullable()->after('tenant_id');
            $table->index(['tenant_id', 'program_id'], 'leads_tenant_program');
            $table->index(['program_id', 'stage'], 'leads_program_stage');
        });

        // Add program_id to activity_logs for program-scoped audit events.
        // Never put referrer/partner UUIDs in activity_logs.user_id;
        // actor IDs for non-staff go in metadata.
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('program_id')->nullable()->after('tenant_id');
            $table->index(['tenant_id', 'program_id'], 'activity_logs_tenant_program');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_tenant_program');
            $table->dropColumn('program_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_program_stage');
            $table->dropIndex('leads_tenant_program');
            $table->dropColumn('program_id');
        });
    }
};
