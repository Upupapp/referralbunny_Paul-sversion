<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index(['tenant_id', 'archived_at'], 'leads_tenant_archived_at_index');
            $table->index(['tenant_id', 'deleted_at'],  'leads_tenant_deleted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_tenant_archived_at_index');
            $table->dropIndex('leads_tenant_deleted_at_index');
        });
    }
};
