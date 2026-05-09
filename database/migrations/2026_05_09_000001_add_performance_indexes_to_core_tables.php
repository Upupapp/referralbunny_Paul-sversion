<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Helper: only add index if it doesn't already exist
        $addIndex = function (string $table, array $columns, string $name) {
            $exists = DB::select(
                "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                [$table, $name]
            );
            if (empty($exists)) {
                $cols = implode(', ', $columns);
                DB::statement("CREATE INDEX {$name} ON {$table} ({$cols})");
            }
        };

        // leads — most frequently filtered table
        $addIndex('leads', ['tenant_id', 'status'],              'idx_leads_tenant_status');
        $addIndex('leads', ['tenant_id', 'stage', 'days_left'],  'idx_leads_tenant_stage_days');
        $addIndex('leads', ['tenant_id', 'organization_id'],     'idx_leads_tenant_org');

        // notifications — queried every page load
        $addIndex('notifications', ['notifiable_type', 'notifiable_id', 'is_read'], 'idx_notifs_notifiable_read');
        $addIndex('notifications', ['tenant_id', 'is_read'],     'idx_notifs_tenant_read');

        // contacts
        $addIndex('contacts', ['tenant_id'],                     'idx_contacts_tenant');

        // organizations
        $addIndex('organizations', ['tenant_id'],                'idx_organizations_tenant');

        // resellers
        $addIndex('resellers', ['tenant_id', 'status'],          'idx_resellers_tenant_status');

        // reseller_agreement_files
        $addIndex('reseller_agreement_files', ['tenant_id', 'is_active'], 'idx_raf_tenant_active');

        // reseller_required_documents
        $addIndex('reseller_required_documents', ['tenant_id', 'is_active'], 'idx_rrd_tenant_active');
    }

    public function down(): void
    {
        $indexes = [
            'idx_leads_tenant_status', 'idx_leads_tenant_stage_days', 'idx_leads_tenant_org',
            'idx_notifs_notifiable_read', 'idx_notifs_tenant_read',
            'idx_contacts_tenant', 'idx_organizations_tenant',
            'idx_resellers_tenant_status', 'idx_raf_tenant_active', 'idx_rrd_tenant_active',
        ];
        foreach ($indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }
};
