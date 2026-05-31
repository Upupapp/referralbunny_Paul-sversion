<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates import_batches and import_rollbacks if they don't already exist.
 * Safe for Supabase environments where these tables are pre-created.
 * Uses nullable imported_by_id for cross-DB test compatibility.
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
                    $table->integer('total_rows')->default(0);
                    $table->uuid('imported_by_id')->nullable();
                    $table->timestamps();
                });
            } catch (\Throwable) {
                return;
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
                    $table->timestamps();
                });
            } catch (\Throwable) {
                return;
            }

            foreach ([['tenant_id', 'status'], ['import_batch_id']] as $cols) {
                try {
                    Schema::table('import_rollbacks', fn (Blueprint $t) => $t->index($cols));
                } catch (\Throwable) {}
            }
        }
    }

    public function down(): void {}
};
