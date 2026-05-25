<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the batch table first (referenced by FK below)
        DB::statement("
            CREATE TABLE IF NOT EXISTS deal_extension_request_batches (
                id                        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id                 TEXT NOT NULL,
                requested_by_reseller_id  TEXT,
                requested_by_role         TEXT NOT NULL DEFAULT 'referrer',
                batch_reference           TEXT,
                shared_reason             TEXT NOT NULL,
                requested_extension_days  INTEGER,
                status                    TEXT NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending','partially_approved','approved','declined','partially_declined','cancelled')),
                total_items               INTEGER NOT NULL DEFAULT 0,
                pending_count             INTEGER NOT NULL DEFAULT 0,
                approved_count            INTEGER NOT NULL DEFAULT 0,
                declined_count            INTEGER NOT NULL DEFAULT 0,
                skipped_count             INTEGER NOT NULL DEFAULT 0,
                submitted_at              TIMESTAMPTZ,
                resolved_at               TIMESTAMPTZ,
                last_decision_at          TIMESTAMPTZ,
                metadata                  JSONB,
                created_at                TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at                TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");

        // Indexes on batch table
        DB::statement("CREATE INDEX IF NOT EXISTS idx_derb_tenant_status   ON deal_extension_request_batches(tenant_id, status)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_derb_tenant_reseller ON deal_extension_request_batches(tenant_id, requested_by_reseller_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_derb_tenant_created  ON deal_extension_request_batches(tenant_id, created_at DESC)");

        // 2. Extend deal_assignment_extension_requests with batch_id and per_deal_note
        DB::statement("ALTER TABLE deal_assignment_extension_requests ADD COLUMN IF NOT EXISTS batch_id UUID REFERENCES deal_extension_request_batches(id) ON DELETE SET NULL");
        DB::statement("ALTER TABLE deal_assignment_extension_requests ADD COLUMN IF NOT EXISTS per_deal_note TEXT");

        // 3. Drop and recreate the status check constraint to add 'skipped'
        DB::statement("ALTER TABLE deal_assignment_extension_requests DROP CONSTRAINT IF EXISTS deal_assignment_extension_requests_status_check");
        DB::statement("
            ALTER TABLE deal_assignment_extension_requests
            ADD CONSTRAINT deal_assignment_extension_requests_status_check
            CHECK (status IN ('pending_review','approved','rejected','clarification_requested','cancelled','expired','skipped'))
        ");

        // 4. Index for batch_id lookups on extension requests
        DB::statement("CREATE INDEX IF NOT EXISTS idx_daer_batch_id ON deal_assignment_extension_requests(batch_id, status)");

        // Partial index: standalone (non-batch) pending requests
        DB::statement("CREATE INDEX IF NOT EXISTS idx_daer_standalone_pending ON deal_assignment_extension_requests(tenant_id, status) WHERE batch_id IS NULL AND status = 'pending_review'");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_daer_standalone_pending");
        DB::statement("DROP INDEX IF EXISTS idx_daer_batch_id");

        DB::statement("ALTER TABLE deal_assignment_extension_requests DROP CONSTRAINT IF EXISTS deal_assignment_extension_requests_status_check");
        DB::statement("
            ALTER TABLE deal_assignment_extension_requests
            ADD CONSTRAINT deal_assignment_extension_requests_status_check
            CHECK (status IN ('pending_review','approved','rejected','clarification_requested','cancelled','expired'))
        ");
        DB::statement("ALTER TABLE deal_assignment_extension_requests DROP COLUMN IF EXISTS per_deal_note");
        DB::statement("ALTER TABLE deal_assignment_extension_requests DROP COLUMN IF EXISTS batch_id");

        DB::statement("DROP INDEX IF EXISTS idx_derb_tenant_created");
        DB::statement("DROP INDEX IF EXISTS idx_derb_tenant_reseller");
        DB::statement("DROP INDEX IF EXISTS idx_derb_tenant_status");
        DB::statement("DROP TABLE IF EXISTS deal_extension_request_batches");
    }
};
