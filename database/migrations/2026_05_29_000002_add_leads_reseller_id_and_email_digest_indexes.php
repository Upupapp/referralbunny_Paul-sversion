<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX IF NOT EXISTS idx_leads_tenant_reseller_id ON leads(tenant_id, reseller_id) WHERE deleted_at IS NULL AND reseller_id IS NOT NULL");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_leads_tenant_reseller_name ON leads(tenant_id, reseller_name) WHERE deleted_at IS NULL AND reseller_name IS NOT NULL AND reseller_name <> ''");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_email_digests_recipient_topic ON email_digests(recipient_email, topic, tenant_id) WHERE sent_at IS NULL AND failed_at IS NULL");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_email_digests_due ON email_digests(scheduled_at) WHERE sent_at IS NULL AND failed_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_leads_tenant_reseller_id');
        DB::statement('DROP INDEX IF EXISTS idx_leads_tenant_reseller_name');
        DB::statement('DROP INDEX IF EXISTS idx_email_digests_recipient_topic');
        DB::statement('DROP INDEX IF EXISTS idx_email_digests_due');
    }
};
