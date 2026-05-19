<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('critical_action_dismissals', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('user_id');
            $table->string('user_type')->default('tenant_user'); // tenant_user | web
            $table->string('fingerprint');    // MD5 of type:source:related_type:related_id
            $table->string('action_type');
            $table->timestamp('dismissed_at')->useCurrent();
            $table->timestamp('expires_at')->nullable(); // null = permanent; datetime = auto-resurface

            $table->index(['tenant_id', 'user_id', 'user_type']);
            $table->index('fingerprint');
            // One dismissal per user per fingerprint (upsert-safe)
            $table->unique(['tenant_id', 'user_id', 'user_type', 'fingerprint'], 'ca_dismissals_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('critical_action_dismissals');
    }
};
