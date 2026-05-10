<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_comments', function (Blueprint $table) {
            $table->string('client_request_id')->nullable()->after('body');
            $table->index(['tenant_id', 'deal_id', 'client_request_id'], 'idx_dc_idempotency');
        });
    }

    public function down(): void
    {
        Schema::table('deal_comments', function (Blueprint $table) {
            $table->dropIndex('idx_dc_idempotency');
            $table->dropColumn('client_request_id');
        });
    }
};
