<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends import_snapshots and import_rollbacks to support the batch import system
 * (ImportBatch / ImportBatchRow), and adds rollback_status to import_batches.
 *
 * The existing columns (import_job_id etc.) are NOT modified — backward compatible
 * with the legacy ImportJob / ImportService system.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── import_snapshots ─────────────────────────────────────────
        Schema::table('import_snapshots', function (Blueprint $table) {
            $table->uuid('import_batch_id')->nullable()->after('import_job_id');
            $table->uuid('import_batch_row_id')->nullable()->after('import_batch_id');
            $table->string('operation_type')->nullable()->after('action');  // created|updated|merged|overwritten
            $table->jsonb('before_data')->nullable()->after('old_values_json');
            $table->jsonb('after_data')->nullable()->after('new_values_json');
            $table->jsonb('changed_fields')->nullable()->after('after_data');
            $table->boolean('can_rollback')->default(true)->after('changed_fields');
            $table->string('rollback_status')->default('pending')->after('can_rollback'); // pending|rolled_back|skipped|failed|conflict
            $table->text('rollback_reason')->nullable()->after('rollback_status');
            $table->timestamp('rolled_back_at')->nullable()->after('rollback_reason');
            $table->timestamp('updated_at')->nullable()->after('rolled_back_at');

            $table->index('import_batch_id', 'idx_snapshots_batch');
            $table->index('import_batch_row_id', 'idx_snapshots_batch_row');
            $table->index(['entity_type', 'entity_id'], 'idx_snapshots_entity');
        });

        // ── import_rollbacks ─────────────────────────────────────────
        Schema::table('import_rollbacks', function (Blueprint $table) {
            $table->uuid('import_batch_id')->nullable()->after('import_job_id');
            $table->string('requested_by_type')->nullable()->after('requested_by');  // tenant_admin|super_admin
            $table->string('mode')->default('full_batch')->after('requested_by_type');
            $table->jsonb('dry_run_summary')->nullable()->after('rollback_summary_json');
            $table->jsonb('result_summary')->nullable()->after('dry_run_summary');
            $table->jsonb('error_summary')->nullable()->after('result_summary');
            $table->timestamp('started_at')->nullable()->after('completed_at');
            $table->unsignedInteger('records_skipped')->default(0)->after('records_deleted');
            $table->unsignedInteger('records_failed')->default(0)->after('records_skipped');
            $table->unsignedInteger('records_conflict')->default(0)->after('records_failed');

            $table->index('import_batch_id', 'idx_rollbacks_batch');
        });

        // ── import_batches ────────────────────────────────────────────
        Schema::table('import_batches', function (Blueprint $table) {
            $table->string('rollback_status')->default('none')->after('template_adoption_status');
            // none|eligible|processing|completed|completed_with_warnings|failed|not_available
            $table->uuid('rollback_id')->nullable()->after('rollback_status');
        });

        // ── import_batch_rows — track snapshot ID per row ─────────────
        Schema::table('import_batch_rows', function (Blueprint $table) {
            $table->uuid('snapshot_id')->nullable()->after('created_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('import_snapshots', function (Blueprint $table) {
            $table->dropIndex('idx_snapshots_batch');
            $table->dropIndex('idx_snapshots_batch_row');
            $table->dropIndex('idx_snapshots_entity');
            $table->dropColumn([
                'import_batch_id', 'import_batch_row_id', 'operation_type',
                'before_data', 'after_data', 'changed_fields',
                'can_rollback', 'rollback_status', 'rollback_reason',
                'rolled_back_at', 'updated_at',
            ]);
        });

        Schema::table('import_rollbacks', function (Blueprint $table) {
            $table->dropIndex('idx_rollbacks_batch');
            $table->dropColumn([
                'import_batch_id', 'requested_by_type', 'mode',
                'dry_run_summary', 'result_summary', 'error_summary',
                'started_at', 'records_skipped', 'records_failed', 'records_conflict',
            ]);
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn(['rollback_status', 'rollback_id']);
        });

        Schema::table('import_batch_rows', function (Blueprint $table) {
            $table->dropColumn('snapshot_id');
        });
    }
};
