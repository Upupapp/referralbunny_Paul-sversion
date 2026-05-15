<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auth & config performance indexes.
 *
 * Covers:
 *  1. resellers(tenant_id, email)         — login lookup + hasPending query
 *  2. resellers(tenant_id, setup_token)   — password-reset + invite setup lookup
 *  3. partner_users(tenant_id, email)     — partner login lookup
 *  4. partner_users(tenant_id, setup_token) — partner reset/setup lookup
 *  5. tenant_users(tenant_id)             — configMeta + EnsureTenantAccess join
 *  6. tenant_memberships(tenant_user_id, tenant_id, status)
 *                                         — EnsureTenantAccess, dashboard, every page load
 *  7. tenant_legal_agreement_acceptances(tenant_id, user_type, user_id)
 *                                         — EnsureLegalAgreementsAccepted — fires on EVERY
 *                                           authenticated reseller/tenant page request
 *  8. tenant_configs(tenant_id)           — configMeta() called on every tenant page
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // ── 1. Reseller login — WHERE email = ? (already has idx_resellers_email
        //       from migration_v16, but add tenant_id composite for the login +
        //       forgotPassword path that filters both) ────────────────────────
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_resellers_tenant_email
            ON resellers(tenant_id, email)
        ");

        // ── 2. Reseller setup/reset token lookup — WHERE setup_token = ?
        //       (idx_resellers_setup_token already exists from migration_v16;
        //       add a composite WITH tenant_id for the status-check path) ─────
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_resellers_tenant_setup_token
            ON resellers(tenant_id, setup_token)
            WHERE setup_token IS NOT NULL
        ");

        // ── 3. Partner login — WHERE email = ? (tenant-scoped login path) ────
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_partner_users_tenant_email
            ON partner_users(tenant_id, email)
        ");

        // ── 4. Partner setup/reset token lookup ───────────────────────────────
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_partner_users_tenant_setup_token
            ON partner_users(tenant_id, setup_token)
            WHERE setup_token IS NOT NULL
        ");

        // ── 5. tenant_users(tenant_id) — needed for configMeta join path ─────
        //       (standalone email index idx_tenant_users_email already exists)
        //       The EnsureTenantAccess middleware also queries tenant_memberships
        //       by (tenant_user_id, tenant_id, status) on EVERY tenant page load.
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_tenant_memberships_user_tenant_status
            ON tenant_memberships(tenant_user_id, tenant_id, status)
        ");

        // ── 6. tenant_configs(tenant_id) — configMeta() is called on every
        //       tenant page (dashboard, deals, referrers, reports…). No cache.
        //       A single-column index makes the WHERE tenant_id=? lookup instant.
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_tenant_configs_tenant_id
            ON tenant_configs(tenant_id)
        ");

        // ── 7. EnsureLegalAgreementsAccepted::hasPending() fires on every
        //       authenticated page request (reseller + tenant portal routes).
        //       It joins acceptances + agreements on (tenant_id, user_type, user_id).
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_legal_acceptances_tenant_user
            ON tenant_legal_agreement_acceptances(tenant_id, user_type, user_id)
        ");

        // ── 8. TenantLegalAgreement WHERE tenant_id=? AND is_active=true AND is_required=true
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_legal_agreements_tenant_active
            ON tenant_legal_agreements(tenant_id, is_active, is_required)
        ");
    }

    public function down(): void {}
};
