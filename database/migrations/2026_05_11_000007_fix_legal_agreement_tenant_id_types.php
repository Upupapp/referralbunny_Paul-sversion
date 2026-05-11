<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tenant_legal_agreements', 'tenant_legal_agreement_acceptances'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS tenant_id");
            DB::statement("ALTER TABLE {$table} ADD COLUMN tenant_id varchar(36)");
            DB::statement("CREATE INDEX IF NOT EXISTS {$table}_tenant_id_idx ON {$table}(tenant_id)");
        }
    }

    public function down(): void
    {
        foreach (['tenant_legal_agreements', 'tenant_legal_agreement_acceptances'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS tenant_id");
            DB::statement("ALTER TABLE {$table} ADD COLUMN tenant_id uuid");
        }
    }
};
