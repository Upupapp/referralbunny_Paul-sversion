<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('integration_id');
            $table->string('entity_type');  // 'task' | 'deal'
            $table->string('entity_id');    // task UUID or lead UUID
            $table->string('google_event_id');
            $table->string('google_calendar_id')->default('primary');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id', 'integration_id'], 'gcal_events_entity_integration_unique');
            $table->index('integration_id');
            $table->index(['entity_type', 'entity_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_events');
    }
};
