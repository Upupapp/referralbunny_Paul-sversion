<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public bool $withinTransaction = false;

    public function up(): void
    {
        // leads(tenant_id, reseller_id) — used by SendResellerDailySummariesJob per-reseller query
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_tenant_reseller_id
            ON leads(tenant_id, reseller_id)
            WHERE deleted_at IS NULL AND reseller_id IS NOT NULL
        ");

        // leads(tenant_id, reseller_name) — legacy fallback for name-based matching
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_leads_tenant_reseller_name
            ON leads(tenant_id, reseller_name)
            WHERE deleted_at IS NULL AND reseller_name IS NOT NULL AND reseller_name <> ''
        ");

        // email_digests(recipient_email, topic) — used by EmailDigestService::queue() lookup
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_email_digests_recipient_topic
            ON email_digests(recipient_email, topic, tenant_id)
            WHERE sent_at IS NULL AND failed_at IS NULL
        ");

        // email_digests(scheduled_at) — used by EmailDigestService::sendDue()
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_email_digests_due
            ON email_digests(scheduled_at)
            WHERE sent_at IS NULL AND failed_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_leads_tenant_reseller_id');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_leads_tenant_reseller_name');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_email_digests_recipient_topic');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_email_digests_due');
    }
};
