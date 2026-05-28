<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->string('reset_token')->nullable()->after('setup_token');
            $table->timestamp('reset_token_created_at')->nullable()->after('reset_token');
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->dropColumn(['reset_token', 'reset_token_created_at']);
        });
    }
};
