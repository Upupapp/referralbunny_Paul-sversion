<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * QA seeder for local/staging environments only.
 * Creates test tenants, users, deals, and data needed to run qa-audit checks.
 *
 * NEVER run on production. Always uses QA_ prefix labels.
 * Idempotent: safe to run multiple times (uses updateOrInsert).
 *
 * qa-tenant-c is STITCH Phase 2's 3rd matrix tenant. lgu-ids is never
 * referenced or seeded by this class.
 *
 * Usage:
 *   php artisan db:seed --class=ReferralBunnyQaSeeder
 */
class ReferralBunnyQaSeeder extends Seeder
{
    const QA_TENANT_A  = 'qa-tenant-a';
    const QA_TENANT_B  = 'qa-tenant-b';
    const QA_TENANT_C  = 'qa-tenant-c';

    // Hardcoded UUIDs for STITCH archived-deal spec. Must stay in sync with
    // tests/stitch/e2e/partner-deal-archived.spec.ts constant ARCHIVED_DEAL_ID.
    const QA_ARCHIVED_DEAL_ID    = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
    const QA_ARCHIVED_DEAL_DP    = 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb';
    const QA_ARCHIVED_DEAL_SPLIT = 'cccccccc-cccc-4ccc-accc-cccccccccccc';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('ReferralBunnyQaSeeder MUST NOT be run on production.');
            return;
        }

        $this->command->info('[QA Seeder] Starting — environment: ' . app()->environment());

        $this->seedTenants();
        $this->seedTenantUsers();
        $this->seedResellers();
        try { $this->seedDeals(); } catch (\Exception $e) { $this->command->warn('  seedDeals skipped: ' . $e->getMessage()); }
        $this->seedPartnerUsers();
        try { $this->seedArchivedDeal(); } catch (\Exception $e) { $this->command->warn('  seedArchivedDeal skipped: ' . $e->getMessage()); }
        try { $this->seedPartnerSplits(); } catch (\Exception $e) { $this->command->warn('  seedPartnerSplits skipped: ' . $e->getMessage()); }
        try { $this->seedExtensionRequests(); } catch (\Exception $e) { $this->command->warn('  seedExtensionRequests skipped: ' . $e->getMessage()); }
        $this->seedPlatformUser();

        $this->command->info('[QA Seeder] Complete. QA test data created with prefix [QA_].');
    }

    // ── Tenants ───────────────────────────────────────────────────────────

    private function seedTenants(): void
    {
        foreach ([
            ['id' => self::QA_TENANT_A, 'name' => '[QA] Test Tenant A', 'program_name' => '[QA] Program A', 'status' => 'active'],
            ['id' => self::QA_TENANT_B, 'name' => '[QA] Test Tenant B', 'program_name' => '[QA] Program B', 'status' => 'active'],
            ['id' => self::QA_TENANT_C, 'name' => '[QA] Test Tenant C', 'program_name' => '[QA] Program C', 'status' => 'active'],
        ] as $tenant) {
            DB::table('tenants')->updateOrInsert(
                ['id' => $tenant['id']],
                array_merge($tenant, ['created_at' => now(), 'updated_at' => now()])
            );
            $this->command->line("  Tenant: {$tenant['name']}");
        }
    }

    // ── Tenant Users ──────────────────────────────────────────────────────

    private function seedTenantUsers(): void
    {
        $users = [
            [
                'id'         => 'qa-tenant-admin-a',
                'email'      => 'qa-admin-a@referralbunny.ai',
                'first_name' => '[QA] Admin',
                'last_name'  => 'TenantA',
                'password'   => Hash::make('QaPassword123!'),
                'status'     => 'active',
            ],
            [
                'id'         => 'qa-tenant-admin-b',
                'email'      => 'qa-admin-b@referralbunny.ai',
                'first_name' => '[QA] Admin',
                'last_name'  => 'TenantB',
                'password'   => Hash::make('QaPassword123!'),
                'status'     => 'active',
            ],
            [
                'id'         => 'qa-tenant-manager-a',
                'email'      => 'qa-manager-a@referralbunny.ai',
                'first_name' => '[QA] Manager',
                'last_name'  => 'TenantA',
                'password'   => Hash::make('QaPassword123!'),
                'status'     => 'active',
            ],
            [
                'id'         => 'qa-tenant-manager-b',
                'email'      => 'qa-manager-b@referralbunny.ai',
                'first_name' => '[QA] Manager',
                'last_name'  => 'TenantB',
                'password'   => Hash::make('QaPassword123!'),
                'status'     => 'active',
            ],
            [
                'id'         => 'qa-tenant-admin-c',
                'email'      => 'qa-admin-c@referralbunny.ai',
                'first_name' => '[QA] Admin',
                'last_name'  => 'TenantC',
                'password'   => Hash::make('QaPassword123!'),
                'status'     => 'active',
            ],
            [
                'id'         => 'qa-tenant-manager-c',
                'email'      => 'qa-manager-c@referralbunny.ai',
                'first_name' => '[QA] Manager',
                'last_name'  => 'TenantC',
                'password'   => Hash::make('QaPassword123!'),
                'status'     => 'active',
            ],
        ];

        foreach ($users as $user) {
            DB::table('tenant_users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'first_name' => $user['first_name'],
                    'last_name'  => $user['last_name'],
                    'email'      => $user['email'],
                    'password'   => $user['password'],
                    'status'     => $user['status'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $userId = DB::table('tenant_users')->where('email', $user['email'])->value('id');

            // Membership — tenant + role inferred from the id suffix (qa-tenant-{role}-{tenant}).
            $tenantId = match (true) {
                str_ends_with($user['id'], '-b') => self::QA_TENANT_B,
                str_ends_with($user['id'], '-c') => self::QA_TENANT_C,
                default => self::QA_TENANT_A,
            };
            $role = str_contains($user['id'], 'manager') ? 'manager' : 'admin';

            DB::table('tenant_memberships')->updateOrInsert(
                ['tenant_id' => $tenantId, 'tenant_user_id' => $userId],
                [
                    'tenant_id'      => $tenantId,
                    'tenant_user_id' => $userId,
                    'role'           => $role,
                    'status'         => 'active',
                    'joined_at'      => now(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]
            );

            $this->command->line("  TenantUser: {$user['email']} ({$role} in {$tenantId})");
        }
    }

    // ── Resellers (Referrers) ─────────────────────────────────────────────

    private function seedResellers(): void
    {
        $resellers = [
            [
                'id'          => 'qa-reseller-active-a',
                'tenant_id'   => self::QA_TENANT_A,
                'name'        => '[QA] Active Referrer A',
                'email'       => 'qa-referrer-active@referralbunny.ai',
                'status'      => 'active',
                'password'    => Hash::make('QaPassword123!'),
                'is_anonymous'=> false,
            ],
            [
                'id'          => 'qa-reseller-pending-a',
                'tenant_id'   => self::QA_TENANT_A,
                'name'        => '[QA] Pending Referrer A',
                'email'       => 'qa-referrer-pending@referralbunny.ai',
                'status'      => 'invited',
                'setup_token' => Str::random(64),
                'is_anonymous'=> false,
            ],
            [
                'id'          => 'qa-reseller-active-b',
                'tenant_id'   => self::QA_TENANT_B,
                'name'        => '[QA] Active Referrer B',
                'email'       => 'qa-referrer-active-b@referralbunny.ai',
                'status'      => 'active',
                'password'    => Hash::make('QaPassword123!'),
                'is_anonymous'=> false,
            ],
            [
                'id'          => 'qa-reseller-active-c',
                'tenant_id'   => self::QA_TENANT_C,
                'name'        => '[QA] Active Referrer C',
                'email'       => 'qa-referrer-active-c@referralbunny.ai',
                'status'      => 'active',
                'password'    => Hash::make('QaPassword123!'),
                'is_anonymous'=> false,
            ],
        ];

        foreach ($resellers as $reseller) {
            DB::table('resellers')->updateOrInsert(
                ['tenant_id' => $reseller['tenant_id'], 'email' => $reseller['email']],
                array_merge(
                    collect($reseller)->except('id')->all(),
                    [
                        'joined_date'       => now()->toDateString(),
                        'assigned_leads'    => 0,
                        'closed_value'      => 0,
                        'performance_score' => 0,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]
                )
            );
            $this->command->line("  Reseller: {$reseller['name']} ({$reseller['status']})");
        }
    }

    // ── Deals ─────────────────────────────────────────────────────────────

    private function seedDeals(): void
    {
        $deals = [
            [
                'id'            => 'qa-deal-active-a',
                'tenant_id'     => self::QA_TENANT_A,
                'name'          => '[QA] Active Deal — Tenant A',
                'stage'         => 'introduction',
                'status'        => 'active',
                'days_left'     => 10,
                'reseller_name' => '[QA] Active Referrer A',
                'deal_value'    => 500000.00,
                'base_cost'     => 350000.00,
                'added_amount'  => 150000.00,
                'commission_status' => 'pending',
            ],
            [
                'id'            => 'qa-deal-expiring-a',
                'tenant_id'     => self::QA_TENANT_A,
                'name'          => '[QA] Expiring Deal — Tenant A',
                'stage'         => 'presentation',
                'status'        => 'expiring',
                'days_left'     => 2,
                'reseller_name' => '[QA] Active Referrer A',
                'deal_value'    => 300000.00,
                'base_cost'     => 200000.00,
                'added_amount'  => 100000.00,
                'commission_status' => 'pending',
            ],
            [
                'id'            => 'qa-deal-pending-referrer',
                'tenant_id'     => self::QA_TENANT_A,
                'name'          => '[QA] Deal With Pending Referrer',
                'stage'         => 'introduction',
                'status'        => 'active',
                'days_left'     => 14,
                'reseller_name' => '[QA] Pending Referrer A',
                'deal_value'    => 200000.00,
                'base_cost'     => 140000.00,
                'added_amount'  => 60000.00,
                'commission_status' => 'pending',
            ],
            // Tenant B deal — for isolation testing
            [
                'id'            => 'qa-deal-tenant-b',
                'tenant_id'     => self::QA_TENANT_B,
                'name'          => '[QA] Active Deal — Tenant B (isolation test)',
                'stage'         => 'introduction',
                'status'        => 'active',
                'days_left'     => 14,
                'reseller_name' => '[QA] Referrer B',
                'deal_value'    => 400000.00,
                'base_cost'     => 280000.00,
                'added_amount'  => 120000.00,
                'commission_status' => 'pending',
            ],
        ];

        foreach ($deals as $deal) {
            DB::table('leads')->updateOrInsert(
                ['id' => $deal['id']],
                array_merge($deal, [
                    'data'       => '{}',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
            $this->command->line("  Deal: {$deal['name']} ({$deal['status']})");
        }
    }

    // ── Partner Users ─────────────────────────────────────────────────────

    private function seedPartnerUsers(): void
    {
        $partners = [
            [
                'id'         => 'qa-partner-active-a',
                'tenant_id'  => self::QA_TENANT_A,
                'email'      => 'qa-partner-active@referralbunny.ai',
                'first_name' => '[QA] Active',
                'last_name'  => 'Partner A',
                'password'   => Hash::make('QaPassword123!'),
                'status'     => 'active',
                'setup_completed_at' => now(),
            ],
            [
                'id'         => 'qa-partner-pending-a',
                'tenant_id'  => self::QA_TENANT_A,
                'email'      => 'qa-partner-pending@referralbunny.ai',
                'first_name' => '[QA] Pending',
                'last_name'  => 'Partner A',
                'status'     => 'invited',
                'setup_token'=> Str::random(64),
            ],
            [
                'id'         => 'qa-partner-active-b',
                'tenant_id'  => self::QA_TENANT_B,
                'email'      => 'qa-partner-active-b@referralbunny.ai',
                'first_name' => '[QA] Active',
                'last_name'  => 'Partner B',
                'password'   => Hash::make('QaPassword123!'),
                'status'     => 'active',
                'setup_completed_at' => now(),
            ],
            [
                'id'         => 'qa-partner-active-c',
                'tenant_id'  => self::QA_TENANT_C,
                'email'      => 'qa-partner-active-c@referralbunny.ai',
                'first_name' => '[QA] Active',
                'last_name'  => 'Partner C',
                'password'   => Hash::make('QaPassword123!'),
                'status'     => 'active',
                'setup_completed_at' => now(),
            ],
        ];

        // Remove any deal_partners rows carrying non-UUID deal IDs from previous seeder runs.
        if (\Illuminate\Support\Facades\Schema::hasTable('deal_partners')) {
            DB::table('deal_partners')->where('deal_id', 'qa-deal-active-a')->delete();
        }

        foreach ($partners as $partner) {
            DB::table('partner_users')->updateOrInsert(
                ['tenant_id' => $partner['tenant_id'], 'email' => $partner['email']],
                array_merge(
                    collect($partner)->except('id')->all(),
                    ['created_at' => now(), 'updated_at' => now()]
                )
            );
            $this->command->line("  Partner: {$partner['email']} ({$partner['status']})");
        }
    }

    // ── Partner Splits ────────────────────────────────────────────────────

    private function seedPartnerSplits(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('deal_partner_splits')) {
            $this->command->warn('  deal_partner_splits table not found — skipping partner split seed (run migration v41)');
            return;
        }

        // Inserts require UUID-keyed deal and partner rows not available in the QA seed.
        // Skip gracefully rather than emit a PostgreSQL UUID syntax error.
        $this->command->line('  Partner splits: skipped (deal UUID rows not available in QA seed)');
    }

    // ── Extension Requests ─────────────────────────────────────────────────

    private function seedExtensionRequests(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('deal_assignment_extension_requests')) {
            $this->command->warn('  deal_assignment_extension_requests table not found — skipping (run migration v42)');
            return;
        }

        // Inserts require UUID-keyed deal rows not available in the QA seed.
        // Skip gracefully rather than emit a PostgreSQL UUID syntax error.
        $this->command->line('  Extension requests: skipped (deal UUID rows not available in QA seed)');
    }

    // ── Archived Deal (STITCH spec target) ────────────────────────────────

    private function seedArchivedDeal(): void
    {
        // Use a properly-formatted UUID so PostgreSQL's uuid column accepts it.
        DB::table('leads')->updateOrInsert(
            ['id' => self::QA_ARCHIVED_DEAL_ID],
            [
                'id'               => self::QA_ARCHIVED_DEAL_ID,
                'tenant_id'        => self::QA_TENANT_A,
                'name'             => '[QA] Archived Deal — Tenant A',
                'stage'            => 'introduction',
                'status'           => 'archived',
                'archived_at'      => now()->subDays(5),
                'days_left'        => 0,
                'reseller_name'    => '[QA] Archived Referrer A',
                'deal_value'       => 100000.00,
                'base_cost'        => 70000.00,
                'added_amount'     => 30000.00,
                'commission_status'=> 'pending',
                'data'             => '{}',
                'created_at'       => now()->subDays(10),
                'updated_at'       => now()->subDays(5),
            ]
        );
        $this->command->line('  Archived deal: [QA] Archived Deal — Tenant A (STITCH)');

        if (!\Illuminate\Support\Facades\Schema::hasTable('deal_partners')) {
            $this->command->warn('  deal_partners table missing — archived deal_partners row skipped');
            return;
        }

        $partnerId = DB::table('partner_users')
            ->where('email', 'qa-partner-active@referralbunny.ai')
            ->value('id');

        if (!$partnerId) {
            $this->command->warn('  qa-partner-active not found — archived deal_partners row skipped');
            return;
        }

        DB::table('deal_partners')->updateOrInsert(
            ['id' => self::QA_ARCHIVED_DEAL_DP],
            [
                'id'              => self::QA_ARCHIVED_DEAL_DP,
                'tenant_id'       => self::QA_TENANT_A,
                'deal_id'         => self::QA_ARCHIVED_DEAL_ID,
                'partner_user_id' => $partnerId,
                'status'          => 'active',
                'created_at'      => now()->subDays(10),
                'updated_at'      => now()->subDays(5),
            ]
        );
        $this->command->line('  deal_partners: linked qa-partner-active to archived deal');

        if (!\Illuminate\Support\Facades\Schema::hasTable('deal_partner_splits')) {
            $this->command->warn('  deal_partner_splits table missing — archived split row skipped');
            return;
        }

        // $partnerId is guaranteed non-null here — the early return at line 433 exits the
        // method before this point if the partner lookup fails.
        // Also seed a deal_partner_splits row so both paths of authorizedDealIds()
        // are exercised against the archived deal in E2E tests.
        DB::table('deal_partner_splits')->updateOrInsert(
            ['id' => self::QA_ARCHIVED_DEAL_SPLIT],
            [
                'id'              => self::QA_ARCHIVED_DEAL_SPLIT,
                'tenant_id'       => self::QA_TENANT_A,
                'deal_id'         => self::QA_ARCHIVED_DEAL_ID,
                'partner_user_id' => $partnerId,
                'status'          => 'active',
                'deleted_at'      => null,
                'created_at'      => now()->subDays(10),
                'updated_at'      => now()->subDays(5),
            ]
        );
        $this->command->line('  deal_partner_splits: linked qa-partner-active to archived deal (split path)');
    }

    // ── Platform User (Super Admin) ──────────────────────────────────────

    private function seedPlatformUser(): void
    {
        DB::table('users')->updateOrInsert(
            ['email' => 'qa-superadmin@referralbunny.ai'],
            [
                'name'              => '[QA] Super Admin',
                'password'          => Hash::make('QaPassword123!'),
                'email_verified_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        $this->command->line('  PlatformUser: qa-superadmin@referralbunny.ai (super_admin)');
    }
}
