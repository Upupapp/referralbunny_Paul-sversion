<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recent_searches')) {
            try {
                Schema::create('recent_searches', function (Blueprint $table) {
                    $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
                    $table->unsignedBigInteger('user_id');
                    $table->string('query');
                    $table->unsignedInteger('result_count')->default(0);
                    $table->timestamps();
                    $table->index(['user_id', 'created_at']);
                });
            } catch (\Throwable) {}
        }

        if (!Schema::hasTable('saved_searches')) {
            try {
                Schema::create('saved_searches', function (Blueprint $table) {
                    $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
                    $table->unsignedBigInteger('user_id');
                    $table->string('name', 100);
                    $table->string('query');
                    $table->json('filters_json')->nullable();
                    $table->boolean('is_pinned')->default(false);
                    $table->timestamps();
                    $table->index('user_id');
                });
            } catch (\Throwable) {}
        }
    }

    public function down(): void {}
};
