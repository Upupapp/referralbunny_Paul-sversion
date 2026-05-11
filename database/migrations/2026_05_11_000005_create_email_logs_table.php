<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_logs')) return;

        Schema::create('email_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email_key')->index();
            $table->string('recipient_email');
            $table->string('recipient_type')->nullable();
            $table->string('recipient_id')->nullable();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('subject')->nullable();
            $table->string('status', 20)->default('pending'); // pending, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
