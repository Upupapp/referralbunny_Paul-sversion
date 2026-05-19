<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Compound index on (tenant_id, deduplication_key) for the notifications table.
 *
 * NotificationDispatchService::dispatchToTenantAdmins() now performs a short-circuit
 * EXISTS check — WHERE tenant_id = ? AND deduplication_key LIKE '{category}:%:{suffix}'
 * — before loading the admin-list JOIN query on dedup hits.
 *
 * Without this index, that check falls back to a full-table scan (or at best uses the
 * single-column dedup_key index, then re-filters by tenant_id in a heap scan).
 * The compound index lets Postgres satisfy the WHERE tenant_id = ? predicate as an
 * index prefix and then scan only matching dedup rows for that tenant.
 *
 * Partial condition (deduplication_key IS NOT NULL) keeps the index small — the
 * majority of notifications have no dedup key and should not bloat this index.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_notifications_tenant_dedup
            ON notifications (tenant_id, deduplication_key)
            WHERE deduplication_key IS NOT NULL
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_notifications_tenant_dedup');
    }
};
