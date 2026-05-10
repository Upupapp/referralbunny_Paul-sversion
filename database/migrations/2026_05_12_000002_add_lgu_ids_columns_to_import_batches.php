<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds LGU IDS–specific row-count columns to import_batches.
 * These are used by LguIdsImportService::createBatch() and executeImport().
 * Safe to run: each column is guarded with hasColumn().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('import_batches')) {
            return;
        }

        Schema::table('import_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('import_batches', 'duplicate_rows')) {
                $table->unsignedInteger('duplicate_rows')->default(0)->nullable();
            }
            if (!Schema::hasColumn('import_batches', 'unknown_referrer_rows')) {
                $table->unsignedInteger('unknown_referrer_rows')->default(0)->nullable();
            }
            if (!Schema::hasColumn('import_batches', 'pricing_issue_rows')) {
                $table->unsignedInteger('pricing_issue_rows')->default(0)->nullable();
            }
            if (!Schema::hasColumn('import_batches', 'blocked_rows')) {
                $table->unsignedInteger('blocked_rows')->default(0)->nullable();
            }
            if (!Schema::hasColumn('import_batches', 'possible_duplicate_rows')) {
                $table->unsignedInteger('possible_duplicate_rows')->default(0)->nullable();
            }
            if (!Schema::hasColumn('import_batches', 'same_email_different_referrer_rows')) {
                $table->unsignedInteger('same_email_different_referrer_rows')->default(0)->nullable();
            }
            if (!Schema::hasColumn('import_batches', 'started_at')) {
                $table->timestamp('started_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('import_batches')) {
            return;
        }
        Schema::table('import_batches', function (Blueprint $table) {
            $cols = [
                'duplicate_rows', 'unknown_referrer_rows', 'pricing_issue_rows',
                'blocked_rows', 'possible_duplicate_rows',
                'same_email_different_referrer_rows', 'started_at',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('import_batches', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
