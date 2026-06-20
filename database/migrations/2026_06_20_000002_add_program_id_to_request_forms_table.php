<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('request_forms', 'program_id')) {
            Schema::table('request_forms', function (Blueprint $table) {
                $table->string('program_id')->nullable()->after('tenant_id');
                $table->index(['tenant_id', 'program_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('request_forms', 'program_id')) {
            Schema::table('request_forms', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'program_id']);
                $table->dropColumn('program_id');
            });
        }
    }
};
