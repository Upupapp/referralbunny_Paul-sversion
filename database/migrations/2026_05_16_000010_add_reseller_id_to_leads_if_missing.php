<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leads', 'reseller_id')) {
            return;
        }

        try {
            Schema::table('leads', function (Blueprint $table) {
                $table->uuid('reseller_id')->nullable()->after('reseller_name');
            });
        } catch (\Throwable) {
            // Column may exist via Supabase schema; safe to ignore
        }
    }

    public function down(): void {}
};
