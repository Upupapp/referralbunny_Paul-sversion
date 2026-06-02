<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_splits', function (Blueprint $table) {
            // Soft-delete so removed co-referrer splits retain audit trail.
            // $timestamps = false on the model means Laravel won't auto-set
            // created_at/updated_at, but deleted_at is managed by SoftDeletes manually.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('commission_splits', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
