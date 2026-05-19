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
            $table->string('action_type')->default(''); // matches SQL: TEXT NOT NULL DEFAULT ''
            $table->timestampTz('dismissed_at')->useCurrent(); // TIMESTAMPTZ to match Supabase SQL
            $table->timestampTz('expires_at')->nullable(); // null = permanent; datetime = auto-resurface

            $table->index(['tenant_id', 'user_id', 'user_type']);
            // The unique constraint below already covers (tenant_id, user_id, user_type, fingerprint)
            // and is used by upsert(). A bare fingerprint index would be redundant — removed.
            $table->unique(['tenant_id', 'user_id', 'user_type', 'fingerprint'], 'ca_dismissals_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('critical_action_dismissals');
    }
};
