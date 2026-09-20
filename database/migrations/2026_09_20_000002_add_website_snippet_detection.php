<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_connections', function (Blueprint $table) {
            $table->json('tracking_origins')->nullable();
            $table->timestamp('snippet_installed_at')->nullable();
            $table->timestamp('snippet_last_seen_at')->nullable();
            $table->string('snippet_origin')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('program_connections', function (Blueprint $table) {
            $table->dropColumn(['tracking_origins', 'snippet_installed_at', 'snippet_last_seen_at', 'snippet_origin']);
        });
    }
};
