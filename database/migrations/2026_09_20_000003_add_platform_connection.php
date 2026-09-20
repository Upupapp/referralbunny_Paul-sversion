<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('program_connections', function (Blueprint $table) {
        $table->string('platform')->nullable();
        $table->timestamp('platform_connected_at')->nullable();
    }); }
    public function down(): void { Schema::table('program_connections', fn (Blueprint $table) => $table->dropColumn(['platform','platform_connected_at'])); }
};
