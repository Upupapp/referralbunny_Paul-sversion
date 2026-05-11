<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approvals')) {
            return;
        }

        Schema::table('approvals', function (Blueprint $table) {
            if (! Schema::hasColumn('approvals', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('approvals')) {
            return;
        }

        Schema::table('approvals', function (Blueprint $table) {
            if (Schema::hasColumn('approvals', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });
    }
};
