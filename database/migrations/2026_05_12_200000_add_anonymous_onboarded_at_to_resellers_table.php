<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            // Tracks the first time a referrer clicks the eye/anonymous toggle.
            // Onboarding guide recurs until this is set.
            $table->timestamp('anonymous_onboarded_at')->nullable()->after('is_anonymous');
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->dropColumn('anonymous_onboarded_at');
        });
    }
};
