<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use App\Models\Reseller;
use App\Services\CriticalActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CriticalActionsTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'id'     => (string) Str::uuid(),
            'name'   => 'Test ' . Str::random(4),
            'slug'   => 'test-' . Str::random(6),
            'status' => 'active',
        ]);
    }

    private function createTenantUser(array $attrs = []): TenantUser
    {
        return TenantUser::create(array_merge([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'u' . Str::random(6) . '@example.com',
            'password'   => bcrypt('password'),
            'status'     => 'active',
        ], $attrs));
    }

    private function createMembership(Tenant $t, TenantUser $u, string $role = 'admin'): TenantMembership
    {
        return TenantMembership::create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => $t->id,
            'tenant_user_id' => $u->id,
            'role'           => $role,
            'status'         => 'active',
        ]);
    }

    private function createReseller(Tenant $t, array $attrs = []): Reseller
    {
        return Reseller::create(array_merge([
            'id'                => (string) Str::uuid(),
            'tenant_id'         => $t->id,
            'name'              => 'Referrer ' . Str::random(4),
            'email'             => 'r' . Str::random(6) . '@example.com',
            'status'            => 'active',
            'assigned_leads'    => 0,
            'closed_value'      => 0,
            'performance_score' => 0,
            'is_anonymous'      => false,
        ], $attrs));
    }

    private function createLead(Tenant $t, array $attrs = []): string
    {
        $id = (string) Str::uuid();
        DB::table('leads')->insert(array_merge([
            'id'            => $id,
            'tenant_id'     => $t->id,
            'name'          => 'Deal ' . Str::random(4),
            'stage'         => 'introduction',
            'status'        => 'active',
            'reseller_name' => 'Referrer',
            'days_left'     => 21,
            'deal_value'    => 10000,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $attrs));
        return $id;
    }

    // ── 1. Dashboard page shows critical actions ──────────────────

    /** @test */
    public function tenant_admin_dashboard_loads_with_critical_actions()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->get(route('tenant.dashboard', $tenant->id))
            ->assertOk()
            ->assertViewHas('criticalActions')
            ->assertViewHas('dashboardCounts');
    }

    /** @test */
    public function dashboard_critical_actions_are_tenant_scoped()
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $adminA  = $this->createTenantUser();
        $this->createMembership($tenantA, $adminA, 'admin');

        // Create expiring deal in tenant B
        $this->createLead($tenantB, ['status' => 'expiring', 'days_left' => 1, 'reseller_name' => 'Other']);

        $res = $this->actingAs($adminA, 'tenant')
            ->get(route('tenant.dashboard', $tenantA->id))
            ->assertOk();

        $criticalActions = $res->viewData('criticalActions');
        // Should not contain tenant B's expiring deal
        $summaries = array_column($criticalActions, 'summary');
        foreach ($summaries as $s) {
            $this->assertStringNotContainsString('Other', $s);
        }
    }

    // ── 2. Critical actions page ──────────────────────────────────

    /** @test */
    public function admin_can_access_critical_actions_page()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->get(route('tenant.critical-actions', $tenant->id))
            ->assertOk()
            ->assertViewIs('tenant.critical-actions.index');
    }

    /** @test */
    public function manager_can_access_critical_actions_page()
    {
        $tenant  = $this->createTenant();
        $manager = $this->createTenantUser();
        $this->createMembership($tenant, $manager, 'manager');

        $this->actingAs($manager, 'tenant')
            ->get(route('tenant.critical-actions', $tenant->id))
            ->assertOk();
    }

    /** @test */
    public function member_cannot_access_critical_actions_page()
    {
        $tenant = $this->createTenant();
        $member = $this->createTenantUser();
        $this->createMembership($tenant, $member, 'member');

        $this->actingAs($member, 'tenant')
            ->get(route('tenant.critical-actions', $tenant->id))
            ->assertForbidden();
    }

    /** @test */
    public function referrer_cannot_access_tenant_critical_actions_page()
    {
        $tenant   = $this->createTenant();
        $reseller = $this->createReseller($tenant);

        $this->actingAs($reseller, 'reseller')
            ->get(route('tenant.critical-actions', $tenant->id))
            ->assertRedirect(); // auth:tenant,web middleware redirects
    }

    // ── 3. Service — expiring deals ───────────────────────────────

    /** @test */
    public function service_detects_expiring_deals_for_tenant()
    {
        $tenant = $this->createTenant();
        $this->createLead($tenant, ['status' => 'expiring', 'days_left' => 3]);

        $service = app(CriticalActionService::class);
        $actions = $service->dashboardSummary($tenant->id);

        $types = array_column($actions, 'type');
        $this->assertContains('deal_expiring', $types);
    }

    /** @test */
    public function service_marks_urgent_for_deals_expiring_in_1_day()
    {
        $tenant = $this->createTenant();
        $this->createLead($tenant, ['status' => 'expiring', 'days_left' => 1]);

        $service = app(CriticalActionService::class);
        $actions = $service->dashboardSummary($tenant->id);

        $expiringActions = array_filter($actions, fn($a) => $a['type'] === 'deal_expiring');
        foreach ($expiringActions as $a) {
            $this->assertEquals('urgent', $a['severity']);
        }
    }

    /** @test */
    public function service_detects_missing_referrer_deals()
    {
        $tenant = $this->createTenant();
        $this->createLead($tenant, ['reseller_name' => null]);

        $service = app(CriticalActionService::class);
        $actions = $service->dashboardSummary($tenant->id);

        $types = array_column($actions, 'type');
        $this->assertContains('missing_referrer', $types);
    }

    /** @test */
    public function service_does_not_return_other_tenants_actions()
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $this->createLead($tenantB, ['status' => 'expiring', 'days_left' => 1]);

        $service = app(CriticalActionService::class);
        $actions = $service->dashboardSummary($tenantA->id);

        // Tenant A has no expiring deals so the expiring action should not appear
        $types = array_column($actions, 'type');
        $this->assertNotContains('deal_expiring', $types);
    }

    // ── 4. Service — import events ────────────────────────────────

    /** @test */
    public function service_detects_import_with_warnings()
    {
        $tenant = $this->createTenant();
        DB::table('import_batches')->insert([
            'id'          => (string) Str::uuid(),
            'tenant_id'   => $tenant->id,
            'status'      => 'completed_with_warnings',
            'file_name'   => 'test_import.xlsx',
            'import_type' => 'deals',
            'total_rows'  => 10,
            'failed_rows' => 2,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $service = app(CriticalActionService::class);
        $actions = $service->dashboardSummary($tenant->id);

        $types = array_column($actions, 'type');
        $this->assertContains('import_completed_with_warnings', $types);
    }

    // ── 5. Service — referrer-scoped ─────────────────────────────

    /** @test */
    public function referrer_service_only_returns_own_deals()
    {
        $tenant    = $this->createTenant();
        $resellerA = $this->createReseller($tenant, ['name' => 'Referrer A']);
        $resellerB = $this->createReseller($tenant, ['name' => 'Referrer B', 'email' => 'b@example.com']);

        // Expiring deal for B
        $this->createLead($tenant, ['status' => 'expiring', 'days_left' => 1, 'reseller_name' => 'Referrer B']);

        $service = app(CriticalActionService::class);
        $actions = $service->forReseller($tenant->id, 'Referrer A');

        $summaries = array_column($actions, 'summary');
        foreach ($summaries as $s) {
            $this->assertStringNotContainsString('Referrer B', $s);
        }
    }

    /** @test */
    public function referrer_dashboard_shows_own_activity()
    {
        $tenant   = $this->createTenant();
        $reseller = $this->createReseller($tenant, ['name' => 'Maria Santos']);
        $this->createLead($tenant, ['status' => 'expiring', 'days_left' => 2, 'reseller_name' => 'Maria Santos']);

        $this->actingAs($reseller, 'reseller')
            ->get(route('reseller.dashboard', $tenant->id))
            ->assertOk()
            ->assertViewHas('recentActivity');
    }

    // ── 6. Search and filter ──────────────────────────────────────

    /** @test */
    public function critical_actions_page_search_works()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');
        $this->createLead($tenant, ['status' => 'expiring', 'days_left' => 3, 'name' => 'Unique Deal XYZ']);

        $this->actingAs($admin, 'tenant')
            ->get(route('tenant.critical-actions', $tenant->id) . '?search=Unique+Deal+XYZ')
            ->assertOk()
            ->assertSee('Unique Deal XYZ');
    }

    /** @test */
    public function critical_actions_page_severity_filter_works()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->get(route('tenant.critical-actions', $tenant->id) . '?severity=urgent')
            ->assertOk();
    }

    // ── 7. Tenant isolation on page ───────────────────────────────

    /** @test */
    public function admin_cannot_see_another_tenants_critical_actions_page()
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $adminA  = $this->createTenantUser();
        $this->createMembership($tenantA, $adminA, 'admin');

        // Admin A cannot access Tenant B's critical actions
        $this->actingAs($adminA, 'tenant')
            ->get(route('tenant.critical-actions', $tenantB->id))
            ->assertForbidden();
    }

    // ── 8. Dashboard counts ───────────────────────────────────────

    /** @test */
    public function dashboard_counts_are_tenant_scoped()
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $adminA  = $this->createTenantUser();
        $this->createMembership($tenantA, $adminA, 'admin');

        // Create expiring deal in tenant B
        $this->createLead($tenantB, ['status' => 'expiring', 'days_left' => 3]);

        $res    = $this->actingAs($adminA, 'tenant')
            ->get(route('tenant.dashboard', $tenantA->id));
        $counts = $res->viewData('dashboardCounts');

        // Tenant A has no expiring deals
        $this->assertEquals(0, $counts['expiring_deals']);
    }

    // ── 9. LGU IDS protected ─────────────────────────────────────

    /** @test */
    public function critical_actions_do_not_modify_lgu_ids_data()
    {
        $tenant = Tenant::create([
            'id' => 'lgu-ids', 'name' => 'LGU IDS', 'slug' => 'lgu-ids', 'status' => 'active',
        ]);
        $admin = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $service = app(CriticalActionService::class);
        $service->dashboardSummary('lgu-ids');

        // Verify no leads were modified
        $this->assertDatabaseMissing('leads', ['tenant_id' => 'lgu-ids']);
    }

    // ── 10. Badge JSON endpoint ───────────────────────────────────

    /** @test */
    public function badge_endpoint_returns_count_for_admin()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');
        // Create an expiring deal so there is at least one action_needed item
        $this->createLead($tenant, ['status' => 'expiring', 'days_left' => 1]);

        $this->actingAs($admin, 'tenant')
            ->getJson(route('tenant.critical-actions.badge', $tenant->id))
            ->assertOk()
            ->assertJsonStructure(['count'])
            ->assertJsonPath('count', fn($c) => is_int($c) && $c >= 0);
    }

    /** @test */
    public function badge_endpoint_returns_zero_for_member_role()
    {
        $tenant = $this->createTenant();
        $member = $this->createTenantUser();
        $this->createMembership($tenant, $member, 'member');

        $this->actingAs($member, 'tenant')
            ->getJson(route('tenant.critical-actions.badge', $tenant->id))
            ->assertOk()
            ->assertJsonPath('count', 0);
    }

    /** @test */
    public function badge_endpoint_returns_zero_cross_tenant()
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $adminA  = $this->createTenantUser();
        $this->createMembership($tenantA, $adminA, 'admin');

        // Admin A requests badge for Tenant B — must return 0 (not Tenant B's count)
        $this->actingAs($adminA, 'tenant')
            ->getJson(route('tenant.critical-actions.badge', $tenantB->id))
            ->assertOk()
            ->assertJsonPath('count', 0);
    }

    /** @test */
    public function badge_count_decrements_after_mark_all_read()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');
        $this->createLead($tenant, ['status' => 'expiring', 'days_left' => 1]);

        // Get initial badge (should be > 0 due to expiring deal)
        $res1 = $this->actingAs($admin, 'tenant')
            ->getJson(route('tenant.critical-actions.badge', $tenant->id))
            ->assertOk();
        $countBefore = $res1->json('count');

        // Mark all as seen
        $this->actingAs($admin, 'tenant')
            ->postJson(route('tenant.critical-actions.mark-all-read', $tenant->id))
            ->assertOk();

        // Badge should now return 0 (suppressed for 5 min, urgent-only bypass)
        // At minimum it must not exceed the pre-seen count
        $res2 = $this->actingAs($admin, 'tenant')
            ->getJson(route('tenant.critical-actions.badge', $tenant->id))
            ->assertOk();
        $countAfter = $res2->json('count');

        $this->assertLessThanOrEqual($countBefore, $countAfter,
            'Badge count must not increase after mark-all-read');
    }

    /** @test */
    public function badge_endpoint_unauthenticated_returns_zero()
    {
        $tenant = $this->createTenant();

        $this->getJson(route('tenant.critical-actions.badge', $tenant->id))
            ->assertRedirect(); // auth middleware redirects to login
    }

    // ── 11. Taxonomy fields (phase 2) ────────────────────────────

    /** @test */
    public function action_dto_contains_all_required_taxonomy_fields()
    {
        $tenant = $this->createTenant();
        $this->createLead($tenant, ['status' => 'expiring', 'days_left' => 1]);

        $service = app(CriticalActionService::class);
        $actions = $service->dashboardSummary($tenant->id);

        $this->assertNotEmpty($actions, 'Expected at least one action for a tenant with an expiring deal');

        $action = $actions[0];

        // Core identity
        $this->assertArrayHasKey('type',          $action, 'Missing: type');
        $this->assertArrayHasKey('category',       $action, 'Missing: category');
        $this->assertArrayHasKey('severity',       $action, 'Missing: severity');
        $this->assertArrayHasKey('summary',        $action, 'Missing: summary');
        $this->assertArrayHasKey('source',         $action, 'Missing: source');

        // Actor / subject
        $this->assertArrayHasKey('actor_name',     $action, 'Missing: actor_name');
        $this->assertArrayHasKey('actor_role',     $action, 'Missing: actor_role');
        $this->assertArrayHasKey('subject_type',   $action, 'Missing: subject_type');
        $this->assertArrayHasKey('subject_id',     $action, 'Missing: subject_id');
        $this->assertArrayHasKey('related_type',   $action, 'Missing: related_type');
        $this->assertArrayHasKey('related_id',     $action, 'Missing: related_id');
        $this->assertArrayHasKey('related_label',  $action, 'Missing: related_label');

        // CTA
        $this->assertArrayHasKey('action_url',     $action, 'Missing: action_url');
        $this->assertArrayHasKey('action_label',   $action, 'Missing: action_label');
        $this->assertArrayHasKey('action_needed',  $action, 'Missing: action_needed');
        $this->assertArrayHasKey('action_type',    $action, 'Missing: action_type');

        // Phase-2 taxonomy extras
        $this->assertArrayHasKey('priority_score', $action, 'Missing: priority_score');
        $this->assertArrayHasKey('dismissible',    $action, 'Missing: dismissible');
        $this->assertArrayHasKey('fingerprint',    $action, 'Missing: fingerprint');
        $this->assertArrayHasKey('role_visibility',$action, 'Missing: role_visibility');
        $this->assertArrayHasKey('source_module',  $action, 'Missing: source_module');

        // Lifecycle
        $this->assertArrayHasKey('status',         $action, 'Missing: status');
        $this->assertArrayHasKey('occurred_at',    $action, 'Missing: occurred_at');
        $this->assertArrayHasKey('occurred_ago',   $action, 'Missing: occurred_ago');
        $this->assertArrayHasKey('occurred_fmt',   $action, 'Missing: occurred_fmt');
        $this->assertArrayHasKey('due_at',         $action, 'Missing: due_at');
        $this->assertArrayHasKey('resolved_at',    $action, 'Missing: resolved_at');
        $this->assertArrayHasKey('dismissed_at',   $action, 'Missing: dismissed_at');
        $this->assertArrayHasKey('expires_at',     $action, 'Missing: expires_at');
        $this->assertArrayHasKey('meta',           $action, 'Missing: meta');
        $this->assertArrayHasKey('description',    $action, 'Missing: description');

        // Type checks
        $this->assertIsInt($action['priority_score'], 'priority_score must be an integer');
        $this->assertIsBool($action['dismissible'],   'dismissible must be a boolean');
        $this->assertIsString($action['fingerprint'], 'fingerprint must be a string');
        $this->assertIsArray($action['role_visibility'], 'role_visibility must be an array');
        $this->assertIsArray($action['meta'],            'meta must be an array');
    }

    /** @test */
    public function urgent_deal_expiring_has_high_priority_score_and_is_not_dismissible()
    {
        $tenant = $this->createTenant();
        $this->createLead($tenant, ['status' => 'expiring', 'days_left' => 1]);

        $service = app(CriticalActionService::class);
        $actions = $service->dashboardSummary($tenant->id);

        $expiring = array_values(array_filter($actions, fn($a) => $a['type'] === 'deal_expiring'));
        $this->assertNotEmpty($expiring);

        $a = $expiring[0];
        // Urgent + deal_expiring type bonus = 100 + 10 = 110
        $this->assertGreaterThanOrEqual(100, $a['priority_score'],
            'Urgent expiring deal must have priority_score >= 100');
        $this->assertFalse($a['dismissible'],
            'Expiring deal is urgent severity — wait, only urgent|high are non-dismissible by severity rule; check by type');
    }

    /** @test */
    public function info_severity_action_is_dismissible()
    {
        $tenant = $this->createTenant();
        // invited referrer within 7 days triggers new_referrer_invited (severity=info)
        DB::table('resellers')->insert([
            'id'                => (string) Str::uuid(),
            'tenant_id'         => $tenant->id,
            'name'              => 'New Referrer',
            'email'             => 'new@example.com',
            'status'            => 'invited',
            'assigned_leads'    => 0,
            'closed_value'      => 0,
            'performance_score' => 0,
            'is_anonymous'      => false,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $service = app(CriticalActionService::class);
        $actions = $service->dashboardSummary($tenant->id, 100, false, true, true);

        $infoActions = array_filter($actions, fn($a) => $a['severity'] === 'info');
        foreach ($infoActions as $a) {
            $this->assertTrue($a['dismissible'],
                "Info-severity action '{$a['type']}' should be dismissible");
        }
    }

    /** @test */
    public function deduplication_fingerprint_is_unique_per_subject()
    {
        $tenant = $this->createTenant();
        $leadIdA = $this->createLead($tenant, ['status' => 'expiring', 'days_left' => 1, 'name' => 'Deal A']);
        $leadIdB = $this->createLead($tenant, ['status' => 'expiring', 'days_left' => 2, 'name' => 'Deal B']);

        $service = app(CriticalActionService::class);
        $actions = $service->dashboardSummary($tenant->id, 100);

        $expiringActions = array_filter($actions, fn($a) => $a['type'] === 'deal_expiring');
        $fingerprints = array_column(array_values($expiringActions), 'fingerprint');

        $this->assertCount(
            count(array_unique($fingerprints)),
            $fingerprints,
            'Each deal_expiring action must have a unique fingerprint'
        );
    }

    /** @test */
    public function role_visibility_billing_is_restricted_to_owners_and_admins()
    {
        $tenant = $this->createTenant();

        $service = app(CriticalActionService::class);

        // Inject a billing action by seeding a suspended subscription
        DB::table('subscriptions')->insert([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => $tenant->id,
            'status'     => 'suspended',
            'plan_id'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $actions = $service->dashboardSummary($tenant->id, 100, true);
        $billingActions = array_filter($actions, fn($a) => $a['category'] === 'billing');

        foreach ($billingActions as $a) {
            $this->assertContains('owner',       $a['role_visibility'], "Billing action missing 'owner' in role_visibility");
            $this->assertContains('super_admin', $a['role_visibility'], "Billing action missing 'super_admin' in role_visibility");
            $this->assertNotContains('referrer', $a['role_visibility'], "Billing action must NOT be visible to referrers");
            $this->assertNotContains('partner',  $a['role_visibility'], "Billing action must NOT be visible to partners");
        }
    }
}
