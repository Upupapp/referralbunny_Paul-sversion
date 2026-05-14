<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `type` (nullable string) to the organizations table.
 * Used by LguIdsImportService to tag LGU records as 'government'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('organizations', 'type')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('type', 100)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'type')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
