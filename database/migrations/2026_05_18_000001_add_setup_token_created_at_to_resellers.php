<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('resellers', 'setup_token_created_at')) {
            Schema::table('resellers', function (Blueprint $table) {
                $table->timestamp('setup_token_created_at')->nullable()->after('setup_token');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('resellers', 'setup_token_created_at')) {
            Schema::table('resellers', function (Blueprint $table) {
                $table->dropColumn('setup_token_created_at');
            });
        }
    }
};
