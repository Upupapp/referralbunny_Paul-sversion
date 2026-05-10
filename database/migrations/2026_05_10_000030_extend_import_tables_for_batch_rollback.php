<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        $isPgsql = DB::getDriverName() === 'pgsql';

        // ── import_snapshots ─────────────────────────────────────────
        if (Schema::hasTable('import_snapshots')) {
            Schema::table('import_snapshots', function (Blueprint $table) use ($isPgsql) {
                if (!Schema::hasColumn('import_snapshots', 'import_batch_id')) {
                    $table->uuid('import_batch_id')->nullable()->after('import_job_id');
                }
                if (!Schema::hasColumn('import_snapshots', 'import_batch_row_id')) {
                    $table->uuid('import_batch_row_id')->nullable()->after('import_batch_id');
                }
                if (!Schema::hasColumn('import_snapshots', 'operation_type')) {
                    $table->string('operation_type')->nullable()->after('action');
                }
                if (!Schema::hasColumn('import_snapshots', 'before_data')) {
                    $isPgsql ? $table->jsonb('before_data')->nullable()->after('old_values_json')
                             : $table->json('before_data')->nullable()->after('old_values_json');
                }
                if (!Schema::hasColumn('import_snapshots', 'after_data')) {
                    $isPgsql ? $table->jsonb('after_data')->nullable()->after('new_values_json')
                             : $table->json('after_data')->nullable()->after('new_values_json');
                }
                if (!Schema::hasColumn('import_snapshots', 'changed_fields')) {
                    $isPgsql ? $table->jsonb('changed_fields')->nullable()->after('after_data')
                             : $table->json('changed_fields')->nullable()->after('after_data');
                }
                if (!Schema::hasColumn('import_snapshots', 'can_rollback')) {
                    $table->boolean('can_rollback')->default(true)->after('changed_fields');
                }
                if (!Schema::hasColumn('import_snapshots', 'rollback_status')) {
                    $table->string('rollback_status')->default('pending')->after('can_rollback');
                }
                if (!Schema::hasColumn('import_snapshots', 'rollback_reason')) {
                    $table->text('rollback_reason')->nullable()->after('rollback_status');
                }
                if (!Schema::hasColumn('import_snapshots', 'rolled_back_at')) {
                    $table->timestamp('rolled_back_at')->nullable()->after('rollback_reason');
                }
                if (!Schema::hasColumn('import_snapshots', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable()->after('rolled_back_at');
                }
                try { $table->index('import_batch_id', 'idx_snapshots_batch'); } catch (\Throwable) {}
                try { $table->index('import_batch_row_id', 'idx_snapshots_batch_row'); } catch (\Throwable) {}
                try { $table->index(['entity_type', 'entity_id'], 'idx_snapshots_entity'); } catch (\Throwable) {}
            });
        }

        // ── import_rollbacks ─────────────────────────────────────────
        if (Schema::hasTable('import_rollbacks')) {
            Schema::table('import_rollbacks', function (Blueprint $table) use ($isPgsql) {
                if (!Schema::hasColumn('import_rollbacks', 'import_batch_id')) {
                    $table->uuid('import_batch_id')->nullable()->after('import_job_id');
                }
                if (!Schema::hasColumn('import_rollbacks', 'tenant_id')) {
                    $table->string('tenant_id')->nullable()->after('import_batch_id');
                }
                if (!Schema::hasColumn('import_rollbacks', 'requested_by_type')) {
                    $table->string('requested_by_type')->nullable()->after('requested_by');
                }
                if (!Schema::hasColumn('import_rollbacks', 'mode')) {
                    $table->string('mode')->default('full_batch')->after('requested_by_type');
                }
                if (!Schema::hasColumn('import_rollbacks', 'dry_run_summary')) {
                    $isPgsql ? $table->jsonb('dry_run_summary')->nullable()->after('rollback_summary_json')
                             : $table->json('dry_run_summary')->nullable()->after('rollback_summary_json');
                }
                if (!Schema::hasColumn('import_rollbacks', 'result_summary')) {
                    $isPgsql ? $table->jsonb('result_summary')->nullable()->after('dry_run_summary')
                             : $table->json('result_summary')->nullable()->after('dry_run_summary');
                }
                if (!Schema::hasColumn('import_rollbacks', 'error_summary')) {
                    $isPgsql ? $table->jsonb('error_summary')->nullable()->after('result_summary')
                             : $table->json('error_summary')->nullable()->after('result_summary');
                }
                if (!Schema::hasColumn('import_rollbacks', 'started_at')) {
                    $table->timestamp('started_at')->nullable()->after('completed_at');
                }
                if (!Schema::hasColumn('import_rollbacks', 'records_skipped')) {
                    $table->unsignedInteger('records_skipped')->default(0)->after('records_deleted');
                }
                if (!Schema::hasColumn('import_rollbacks', 'records_failed')) {
                    $table->unsignedInteger('records_failed')->default(0)->after('records_skipped');
                }
                if (!Schema::hasColumn('import_rollbacks', 'records_conflict')) {
                    $table->unsignedInteger('records_conflict')->default(0)->after('records_failed');
                }
                try { $table->index('import_batch_id', 'idx_rollbacks_batch'); } catch (\Throwable) {}
            });
        }

        // ── import_batches ────────────────────────────────────────────
        if (Schema::hasTable('import_batches')) {
            Schema::table('import_batches', function (Blueprint $table) {
                if (!Schema::hasColumn('import_batches', 'rollback_status')) {
                    $table->string('rollback_status')->default('none')->after('template_adoption_status');
                }
                if (!Schema::hasColumn('import_batches', 'rollback_id')) {
                    $table->uuid('rollback_id')->nullable()->after('rollback_status');
                }
            });
        }

        // ── import_batch_rows — track snapshot ID per row ─────────────
        if (Schema::hasTable('import_batch_rows')) {
            Schema::table('import_batch_rows', function (Blueprint $table) {
                if (!Schema::hasColumn('import_batch_rows', 'snapshot_id')) {
                    $table->uuid('snapshot_id')->nullable()->after('created_contact_id');
                }
            });
        }
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
                'import_batch_id', 'tenant_id', 'requested_by_type', 'mode',
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
