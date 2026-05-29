<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_digests', function (Blueprint $table) {
            if (!Schema::hasColumn('email_digests', 'recipient_type')) {
                $table->string('recipient_type')->default('reseller')->after('recipient_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_digests', function (Blueprint $table) {
            $table->dropColumn('recipient_type');
        });
    }
};
