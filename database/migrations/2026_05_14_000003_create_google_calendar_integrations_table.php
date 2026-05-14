<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('tenant_user_id');
            $table->text('access_token')->nullable();         // encrypted
            $table->text('refresh_token')->nullable();        // encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->string('google_calendar_id')->default('primary');
            $table->string('google_email')->nullable();       // display only
            $table->text('scopes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique('tenant_user_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_integrations');
    }
};
