<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgu_ids_referrer_note_reminder_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('referrer_id');
            $table->string('week_key', 8);          // ISO year+week e.g. "202622"
            $table->jsonb('deal_ids')->default('[]');
            $table->unsignedSmallInteger('deal_count')->default(0);
            $table->uuid('notification_id')->nullable();
            $table->uuid('email_log_id')->nullable();
            $table->string('status', 30)->default('sent'); // sent | skipped | failed
            $table->timestamp('sent_at')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->unique(['tenant_id', 'referrer_id', 'week_key'], 'urrl_tenant_referrer_week_unique');
            $table->index('tenant_id', 'urrl_tenant_idx');
            $table->index(['referrer_id', 'week_key'], 'urrl_referrer_week_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgu_ids_referrer_note_reminder_logs');
    }
};
