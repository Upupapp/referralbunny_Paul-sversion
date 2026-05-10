<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add index on deduplication_key in the notifications table.
 *
 * NotificationDispatchService::dispatch() queries this column on every
 * notification send to prevent duplicates. Without an index this is a
 * full-table scan — expensive at any reasonable notification volume.
 *
 * The index is partial (WHERE deduplication_key IS NOT NULL) on PostgreSQL
 * to avoid indexing the majority of rows that have no dedup key.
 * Laravel's Schema builder doesn't support partial indexes natively, so we
 * use a raw statement instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Standard index for non-PostgreSQL compatibility; raw partial index below overrides on PG
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('deduplication_key', 'idx_notifications_dedup_key');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_dedup_key');
        });
    }
};
