<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_completion_responses') &&
            Schema::hasColumn('task_completion_responses', 'recipient_email')) {
            Schema::table('task_completion_responses', function (Blueprint $table) {
                $table->string('recipient_email')->nullable()->change();
                $table->string('recipient_name')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('task_completion_responses')) {
            Schema::table('task_completion_responses', function (Blueprint $table) {
                $table->string('recipient_email')->nullable(false)->change();
                $table->string('recipient_name')->nullable(false)->change();
            });
        }
    }
};
