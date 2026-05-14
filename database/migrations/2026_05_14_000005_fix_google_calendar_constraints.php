<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix: unique constraint on google_calendar_integrations must be (tenant_id, tenant_user_id)
        // so one user can have separate integrations per tenant (not one globally).
        Schema::table('google_calendar_integrations', function (Blueprint $table) {
            $table->dropUnique(['tenant_user_id']);
            $table->unique(['tenant_id', 'tenant_user_id'], 'gcal_integrations_tenant_user_unique');
        });

        // Add FK cascade on google_calendar_events → google_calendar_integrations
        Schema::table('google_calendar_events', function (Blueprint $table) {
            $table->foreign('integration_id', 'gcal_events_integration_fk')
                ->references('id')
                ->on('google_calendar_integrations')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('google_calendar_events', function (Blueprint $table) {
            $table->dropForeign('gcal_events_integration_fk');
        });

        Schema::table('google_calendar_integrations', function (Blueprint $table) {
            $table->dropUnique('gcal_integrations_tenant_user_unique');
            $table->unique('tenant_user_id');
        });
    }
};
