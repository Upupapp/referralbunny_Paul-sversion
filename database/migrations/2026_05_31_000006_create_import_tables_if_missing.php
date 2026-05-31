<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates import_batches and import_rollbacks if they don't already exist.
 * Safe for Supabase environments where these tables are pre-created (both hasTable()
 * guards return true and both blocks are skipped entirely).
 *
 * On fresh DB environments (SQLite CI, staging clones): creates a full-schema replica
 * so that CriticalActionService::importEvents(), badgeCount(), ImportBatch model
 * operations, and ImportRollback::markFailed() / markCompleted() all work correctly.
 *
 * NOTE: Additional columns (file_path, duplicate_rows, same_email_*, etc.) are added
 * by 2026_05_12_000002_add_lgu_ids_columns_to_import_batches.php via hasColumn() guards.
 * Those additive migrations run after this one and are safe to run on top of this schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('import_batches')) {
            try {
                Schema::create('import_batches', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->string('tenant_id');
                    $table->string('import_type');
                    $table->string('file_name');
                    $table->string('status')->default('pending');
                    $table->string('rollback_status')->nullable();
                    $table->unsignedInteger('total_rows')->default(0);
                    $table->unsignedInteger('successful_rows')->default(0);
                    $table->unsignedInteger('failed_rows')->default(0);
                    $table->unsignedInteger('unknown_referrer_rows')->default(0);
                    $table->string('imported_by_role')->nullable();
                    $table->uuid('imported_by_id')->nullable();
                    $table->uuid('rollback_id')->nullable();
                    $table->timestamp('started_at')->nullable();
                    $table->timestamp('completed_at')->nullable();
                    $table->timestamps();
                });
            } catch (\Throwable) {
                // Table creation failed — do not return; import_rollbacks is independent
            }

            foreach ([['tenant_id', 'status'], ['tenant_id', 'import_type']] as $cols) {
                try {
                    Schema::table('import_batches', fn (Blueprint $t) => $t->index($cols));
                } catch (\Throwable) {}
            }
        }

        if (!Schema::hasTable('import_rollbacks')) {
            try {
                Schema::create('import_rollbacks', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->uuid('import_batch_id');
                    $table->string('tenant_id');
                    $table->string('status')->default('pending');
                    $table->string('mode')->default('full');
                    $table->string('requested_by')->nullable();
                    $table->string('requested_by_type')->nullable();
                    $table->unsignedInteger('records_restored')->default(0);
                    $table->unsignedInteger('records_deleted')->default(0);
                    $table->unsignedInteger('records_failed')->default(0);
                    $table->unsignedInteger('records_skipped')->default(0);
                    $table->unsignedInteger('records_conflict')->default(0);
                    $table->json('error_summary')->nullable();
                    $table->timestamp('started_at')->nullable();
                    $table->timestamp('completed_at')->nullable();
                    $table->timestamps();
                });
            } catch (\Throwable) {}

            foreach ([['tenant_id', 'status'], ['import_batch_id']] as $cols) {
                try {
                    Schema::table('import_rollbacks', fn (Blueprint $t) => $t->index($cols));
                } catch (\Throwable) {}
            }
        }
    }

    // Intentionally empty: tables are shared with Supabase; dropping them would corrupt production data.
    // CI environments should use a fresh DB rather than rolling back this migration.
    public function down(): void {}
};
