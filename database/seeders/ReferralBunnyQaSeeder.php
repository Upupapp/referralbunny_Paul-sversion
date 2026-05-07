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
 * Usage:
 *   php artisan db:seed --class=ReferralBunnyQaSeeder
 */
class ReferralBunnyQaSeeder extends Seeder
{
    const QA_TENANT_A  = 'qa-tenant-a';
    const QA_TENANT_B  = 'qa-tenant-b';

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
        $this->seedDeals();
        $this->seedPartnerUsers();
        $this->seedPartnerSplits();
        $this->seedExtensionRequests();

        $this->command->info('[QA Seeder] Complete. QA test data created with prefix [QA_].');
    }

    // ── Tenants ───────────────────────────────────────────────────────────

    private function seedTenants(): void
    {
        foreach ([
            ['id' => self::QA_TENANT_A, 'name' => '[QA] Test Tenant A', 'status' => 'active', 'plan' => 'starter'],
            ['id' => self::QA_TENANT_B, 'name' => '[QA] Test Tenant B', 'status' => 'active', 'plan' => 'starter'],
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
        ];

        foreach ($users as $user) {
            DB::table('tenant_users')->updateOrInsert(
                ['id' => $user['id']],
                array_merge($user, ['created_at' => now(), 'updated_at' => now()])
            );

            // Membership
            $tenantId = str_contains($user['last_name'], 'B') ? self::QA_TENANT_B : self::QA_TENANT_A;
            $role     = str_contains($user['last_name'], 'Manager') ? 'manager' : 'admin';

            DB::table('tenant_memberships')->updateOrInsert(
                ['tenant_id' => $tenantId, 'tenant_user_id' => $user['id']],
                [
                    'id'         => 'qa-mem-' . $user['id'],
                    'tenant_id'  => $tenantId,
                    'tenant_user_id' => $user['id'],
                    'role'       => $role,
                    'status'     => 'active',
                    'joined_at'  => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
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
        ];

        foreach ($resellers as $reseller) {
            DB::table('resellers')->updateOrInsert(
                ['id' => $reseller['id']],
                array_merge($reseller, [
                    'joined_date'       => now()->toDateString(),
                    'assigned_leads'    => 0,
                    'closed_value'      => 0,
                    'performance_score' => 0,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ])
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
        ];

        foreach ($partners as $partner) {
            DB::table('partner_users')->updateOrInsert(
                ['id' => $partner['id']],
                array_merge($partner, ['created_at' => now(), 'updated_at' => now()])
            );
            $this->command->line("  Partner: {$partner['email']} ({$partner['status']})");
        }

        // Link active partner to active deal
        DB::table('deal_partners')->updateOrInsert(
            ['deal_id' => 'qa-deal-active-a', 'partner_user_id' => 'qa-partner-active-a'],
            [
                'id'             => 'qa-deal-partner-link',
                'tenant_id'      => self::QA_TENANT_A,
                'deal_id'        => 'qa-deal-active-a',
                'partner_user_id'=> 'qa-partner-active-a',
                'status'         => 'active',
                'permissions'    => '{}',
                'invited_at'     => now(),
                'accepted_at'    => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]
        );
    }

    // ── Partner Splits ────────────────────────────────────────────────────

    private function seedPartnerSplits(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('deal_partner_splits')) {
            $this->command->warn('  deal_partner_splits table not found — skipping partner split seed (run migration v41)');
            return;
        }

        DB::table('deal_partner_splits')->updateOrInsert(
            ['id' => 'qa-split-active'],
            [
                'id'               => 'qa-split-active',
                'tenant_id'        => self::QA_TENANT_A,
                'deal_id'          => 'qa-deal-active-a',
                'partner_user_id'  => 'qa-partner-active-a',
                'partner_name'     => '[QA] Active Partner A',
                'partner_email'    => 'qa-partner-active@referralbunny.ai',
                'split_share_value'=> 20.0,
                'split_share_type' => 'percentage',
                'currency'         => 'PHP',
                'status'           => 'active',
                'source'           => 'manual',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        DB::table('deal_partner_splits')->updateOrInsert(
            ['id' => 'qa-split-pending'],
            [
                'id'               => 'qa-split-pending',
                'tenant_id'        => self::QA_TENANT_A,
                'deal_id'          => 'qa-deal-expiring-a',
                'partner_name'     => '[QA] Pending Partner',
                'partner_email'    => 'qa-partner-pending@referralbunny.ai',
                'split_share_value'=> 15.0,
                'split_share_type' => 'percentage',
                'currency'         => 'PHP',
                'status'           => 'pending_invite',
                'source'           => 'manual',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        $this->command->line('  Partner splits seeded: 1 active, 1 pending');
    }

    // ── Extension Requests ─────────────────────────────────────────────────

    private function seedExtensionRequests(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('deal_assignment_extension_requests')) {
            $this->command->warn('  deal_assignment_extension_requests table not found — skipping (run migration v42)');
            return;
        }

        DB::table('deal_assignment_extension_requests')->updateOrInsert(
            ['id' => 'qa-ext-req-pending'],
            [
                'id'                  => 'qa-ext-req-pending',
                'tenant_id'           => self::QA_TENANT_A,
                'deal_id'             => 'qa-deal-expiring-a',
                'requested_by_user_id'=> 'qa-reseller-active-a',
                'requested_by_role'   => 'referrer',
                'current_stage'       => 'presentation',
                'current_days_left'   => 2,
                'requested_days'      => 14,
                'reason'              => '[QA] Test extension request for automated QA checks.',
                'status'              => 'pending_review',
                'created_at'          => now(),
                'updated_at'          => now(),
            ]
        );

        $this->command->line('  Extension request seeded: 1 pending_review');
    }
}
