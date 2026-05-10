<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('deal_comments')) {
            return;
        }
        Schema::table('deal_comments', function (Blueprint $table) {
            if (!Schema::hasColumn('deal_comments', 'client_request_id')) {
                $table->string('client_request_id')->nullable()->after('body');
            }
            if (!$this->indexExists('deal_comments', 'idx_dc_idempotency')) {
                $table->index(['tenant_id', 'deal_id', 'client_request_id'], 'idx_dc_idempotency');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('deal_comments')) {
            return;
        }
        Schema::table('deal_comments', function (Blueprint $table) {
            $table->dropIndex('idx_dc_idempotency');
            $table->dropColumn('client_request_id');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = Schema::getConnection()
                ->getDoctrineSchemaManager()
                ->listTableIndexes($table);
            return isset($indexes[$index]);
        } catch (\Throwable) {
            return false;
        }
    }
};
