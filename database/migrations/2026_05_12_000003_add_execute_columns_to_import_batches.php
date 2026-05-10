<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds columns required by ImportBatch::executeImport() that were missing
 * from the original Supabase-created import_batches table.
 * Every column is guarded with hasColumn() — safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('import_batches')) return;

        Schema::table('import_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('import_batches', 'updated_rows')) {
                $table->unsignedInteger('updated_rows')->default(0)->nullable();
            }
            if (!Schema::hasColumn('import_batches', 'skipped_rows')) {
                $table->unsignedInteger('skipped_rows')->default(0)->nullable();
            }
            if (!Schema::hasColumn('import_batches', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            if (!Schema::hasColumn('import_batches', 'summary_json')) {
                $table->json('summary_json')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('import_batches')) return;
        Schema::table('import_batches', function (Blueprint $table) {
            foreach (['updated_rows', 'skipped_rows', 'completed_at', 'summary_json'] as $col) {
                if (Schema::hasColumn('import_batches', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
