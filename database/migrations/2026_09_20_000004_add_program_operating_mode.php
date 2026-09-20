<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Nullable preserves existing programs without rewriting any tenant data.
        Schema::table('programs', function (Blueprint $table) {
            $table->string('operating_mode', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('programs', fn (Blueprint $table) => $table->dropColumn('operating_mode'));
    }
};
