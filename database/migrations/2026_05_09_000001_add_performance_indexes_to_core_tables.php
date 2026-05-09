<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Only create an index if the table exists AND the index doesn't already exist. */
    private function safeIndex(string $table, array $columns, string $name): void
    {
        if (!Schema::hasTable($table)) return;

        $exists = DB::select(
            "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
            [$table, $name]
        );
        if (empty($exists)) {
            $cols = implode(', ', $columns);
            DB::statement("CREATE INDEX {$name} ON {$table} ({$cols})");
        }
    }

    public function up(): void
    {
        // leads — most frequently filtered table
        $this->safeIndex('leads', ['tenant_id', 'status'],             'idx_leads_tenant_status');
        $this->safeIndex('leads', ['tenant_id', 'stage', 'days_left'], 'idx_leads_tenant_stage_days');
        $this->safeIndex('leads', ['tenant_id', 'organization_id'],    'idx_leads_tenant_org');

        // notifications — queried every page load
        $this->safeIndex('notifications', ['notifiable_type', 'notifiable_id', 'is_read'], 'idx_notifs_notifiable_read');
        $this->safeIndex('notifications', ['tenant_id', 'is_read'],    'idx_notifs_tenant_read');

        // contacts & organizations
        $this->safeIndex('contacts',      ['tenant_id'],               'idx_contacts_tenant');
        $this->safeIndex('organizations', ['tenant_id'],               'idx_organizations_tenant');

        // resellers
        $this->safeIndex('resellers', ['tenant_id', 'status'],         'idx_resellers_tenant_status');

        // Agreement / document tables — only if they exist
        $this->safeIndex('reseller_agreement_files',    ['tenant_id', 'is_active'], 'idx_raf_tenant_active');
        $this->safeIndex('reseller_required_documents', ['tenant_id', 'is_active'], 'idx_rrd_tenant_active');
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
