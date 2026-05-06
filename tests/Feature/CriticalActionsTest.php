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

    // ── 5. Service — reseller-scoped ─────────────────────────────

    /** @test */
    public function reseller_service_only_returns_own_deals()
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
    public function reseller_dashboard_shows_own_activity()
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
}
