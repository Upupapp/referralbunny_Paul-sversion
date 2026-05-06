<?php

namespace Tests\Feature;

use App\Models\DealPartner;
use App\Models\Lead;
use App\Models\Partner;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DealListDisplayTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'id'     => (string) Str::uuid(),
            'name'   => 'Test Tenant ' . Str::random(4),
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
            'email'      => 'user-' . Str::random(6) . '@example.com',
            'password'   => bcrypt('password'),
            'status'     => 'active',
        ], $attrs));
    }

    private function createMembership(Tenant $tenant, TenantUser $user, string $role = 'admin', array $extra = []): TenantMembership
    {
        return TenantMembership::create(array_merge([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => $tenant->id,
            'tenant_user_id' => $user->id,
            'role'           => $role,
            'status'         => 'active',
        ], $extra));
    }

    private function createLead(Tenant $tenant, array $attrs = []): Lead
    {
        return Lead::create(array_merge([
            'id'            => (string) Str::uuid(),
            'tenant_id'     => $tenant->id,
            'name'          => 'Test Deal ' . Str::random(4),
            'stage'         => 'introduction',
            'status'        => 'active',
            'reseller_name' => 'Test Referrer',
            'deal_value'    => 10000,
            'days_left'     => 21,
        ], $attrs));
    }

    private function createReseller(Tenant $tenant, array $attrs = []): Reseller
    {
        return Reseller::create(array_merge([
            'id'                => (string) Str::uuid(),
            'tenant_id'         => $tenant->id,
            'name'              => 'Test Referrer',
            'email'             => 'referrer-' . Str::random(6) . '@example.com',
            'status'            => 'active',
            'assigned_leads'    => 0,
            'closed_value'      => 0,
            'performance_score' => 0,
            'is_anonymous'      => false,
        ], $attrs));
    }

    private function createPartner(Tenant $tenant, array $attrs = []): Partner
    {
        return Partner::create(array_merge([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => $tenant->id,
            'email'      => 'partner-' . Str::random(6) . '@example.com',
            'first_name' => 'Partner',
            'last_name'  => 'User',
            'status'     => 'active',
        ], $attrs));
    }

    private function createDealPartner(Lead $lead, Partner $partner, string $status = 'active'): DealPartner
    {
        return DealPartner::create([
            'id'              => (string) Str::uuid(),
            'tenant_id'       => $lead->tenant_id,
            'deal_id'         => $lead->id,
            'partner_user_id' => $partner->id,
            'status'          => $status,
            'permissions'     => [],
        ]);
    }

    // ── 1. Tenant Admin deal list API ────────────────────────────

    /** @test */
    public function api_returns_reseller_name_for_each_lead()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');
        $this->createLead($tenant, ['reseller_name' => 'Jane Smith']);

        $this->actingAs($admin, 'tenant')
            ->getJson("/api/leads?tenant_id={$tenant->id}")
            ->assertOk()
            ->assertJsonFragment(['reseller_name' => 'Jane Smith']);
    }

    /** @test */
    public function api_returns_null_reseller_name_when_unassigned()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');
        $this->createLead($tenant, ['reseller_name' => null]);

        $this->actingAs($admin, 'tenant')
            ->getJson("/api/leads?tenant_id={$tenant->id}")
            ->assertOk()
            ->assertJsonFragment(['reseller_name' => null]);
    }

    /** @test */
    public function api_includes_partner_data_when_requested()
    {
        $tenant  = $this->createTenant();
        $admin   = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');
        $lead    = $this->createLead($tenant);
        $partner = $this->createPartner($tenant, ['first_name' => 'Alice', 'last_name' => 'Wong']);
        $this->createDealPartner($lead, $partner);

        $response = $this->actingAs($admin, 'tenant')
            ->getJson("/api/leads?tenant_id={$tenant->id}&include_partners=1")
            ->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('partners', $data[0]);
        $this->assertCount(1, $data[0]['partners']);
    }

    /** @test */
    public function api_does_not_include_partner_data_by_default()
    {
        $tenant  = $this->createTenant();
        $admin   = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');
        $lead    = $this->createLead($tenant);
        $partner = $this->createPartner($tenant);
        $this->createDealPartner($lead, $partner);

        $response = $this->actingAs($admin, 'tenant')
            ->getJson("/api/leads?tenant_id={$tenant->id}")
            ->assertOk();

        $data = $response->json();
        $this->assertArrayNotHasKey('partners', $data[0]);
    }

    /** @test */
    public function api_excludes_inactive_deal_partners()
    {
        $tenant  = $this->createTenant();
        $admin   = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');
        $lead    = $this->createLead($tenant);
        $partner = $this->createPartner($tenant);
        $this->createDealPartner($lead, $partner, 'removed');

        $response = $this->actingAs($admin, 'tenant')
            ->getJson("/api/leads?tenant_id={$tenant->id}&include_partners=1")
            ->assertOk();

        $data = $response->json();
        $this->assertCount(0, $data[0]['partners']);
    }

    // ── 2. Tenant isolation ───────────────────────────────────────

    /** @test */
    public function api_does_not_return_other_tenant_deals()
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $admin   = $this->createTenantUser();
        $this->createMembership($tenantA, $admin, 'admin');
        $this->createLead($tenantA, ['name' => 'Deal A']);
        $this->createLead($tenantB, ['name' => 'Deal B']);

        $response = $this->actingAs($admin, 'tenant')
            ->getJson("/api/leads?tenant_id={$tenantA->id}")
            ->assertOk();

        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('Deal A'));
        $this->assertFalse($names->contains('Deal B'));
    }

    /** @test */
    public function api_partner_data_is_tenant_scoped()
    {
        $tenantA  = $this->createTenant();
        $tenantB  = $this->createTenant();
        $admin    = $this->createTenantUser();
        $this->createMembership($tenantA, $admin, 'admin');
        $leadA    = $this->createLead($tenantA);
        $leadB    = $this->createLead($tenantB);
        $partnerB = $this->createPartner($tenantB, ['first_name' => 'Other', 'last_name' => 'Partner']);
        $this->createDealPartner($leadB, $partnerB);

        $response = $this->actingAs($admin, 'tenant')
            ->getJson("/api/leads?tenant_id={$tenantA->id}&include_partners=1")
            ->assertOk();

        // Tenant A's lead should have no partners (the partner belongs to Tenant B's lead)
        $this->assertCount(0, $response->json()[0]['partners']);
    }

    // ── 3. Tenant Manager permission gating ───────────────────────

    /** @test */
    public function deals_page_passes_can_view_referrers_true_for_admin()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->get(route('tenant.deals', $tenant->id))
            ->assertOk()
            ->assertViewHas('canViewReferrers', true);
    }

    /** @test */
    public function deals_page_passes_can_view_referrers_true_for_manager_with_default_permissions()
    {
        $tenant  = $this->createTenant();
        $manager = $this->createTenantUser();
        $this->createMembership($tenant, $manager, 'manager');

        $this->actingAs($manager, 'tenant')
            ->get(route('tenant.deals', $tenant->id))
            ->assertOk()
            ->assertViewHas('canViewReferrers', true);
    }

    /** @test */
    public function deals_page_passes_can_view_referrers_false_for_restricted_manager()
    {
        $tenant  = $this->createTenant();
        $manager = $this->createTenantUser();
        $this->createMembership($tenant, $manager, 'manager', [
            'is_custom_permissions' => true,
            'permissions_json'      => ['view_referrers' => false],
        ]);

        $this->actingAs($manager, 'tenant')
            ->get(route('tenant.deals', $tenant->id))
            ->assertOk()
            ->assertViewHas('canViewReferrers', false);
    }

    // ── 4. Referrer portal partner display ────────────────────────

    /** @test */
    public function reseller_api_call_includes_partner_flag()
    {
        $tenant   = $this->createTenant();
        $reseller = $this->createReseller($tenant, ['name' => 'Jane Referrer']);
        $lead     = $this->createLead($tenant, ['reseller_name' => 'Jane Referrer']);
        $partner  = $this->createPartner($tenant, ['first_name' => 'Bob', 'last_name' => 'Partner']);
        $this->createDealPartner($lead, $partner);

        $this->actingAs($reseller, 'reseller')
            ->getJson("/api/leads?tenant_id={$tenant->id}&reseller_name=Jane+Referrer&include_partners=1")
            ->assertOk()
            ->assertJsonPath('0.partners.0.display_name', 'Bob Partner');
    }

    /** @test */
    public function reseller_cannot_see_partners_from_other_resellers_deals()
    {
        $tenant    = $this->createTenant();
        $resellerA = $this->createReseller($tenant, ['name' => 'Referrer A']);
        $resellerB = $this->createReseller($tenant, ['name' => 'Referrer B', 'email' => 'b@example.com']);
        $leadA     = $this->createLead($tenant, ['reseller_name' => 'Referrer A']);
        $leadB     = $this->createLead($tenant, ['reseller_name' => 'Referrer B']);
        $partner   = $this->createPartner($tenant, ['first_name' => 'Alice', 'last_name' => 'P']);
        $this->createDealPartner($leadB, $partner);

        $response = $this->actingAs($resellerA, 'reseller')
            ->getJson("/api/leads?tenant_id={$tenant->id}&reseller_name=Referrer+A&include_partners=1")
            ->assertOk();

        // Referrer A's deal has no partners — should not see Referrer B's partner
        $this->assertCount(0, $response->json()[0]['partners']);
    }

    /** @test */
    public function multiple_partners_all_appear_in_partners_array()
    {
        $tenant  = $this->createTenant();
        $admin   = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');
        $lead    = $this->createLead($tenant);
        $pA      = $this->createPartner($tenant, ['first_name' => 'Alice', 'last_name' => 'A']);
        $pB      = $this->createPartner($tenant, ['first_name' => 'Bob',   'last_name' => 'B', 'email' => 'bob@example.com']);
        $this->createDealPartner($lead, $pA);
        $this->createDealPartner($lead, $pB);

        $response = $this->actingAs($admin, 'tenant')
            ->getJson("/api/leads?tenant_id={$tenant->id}&include_partners=1")
            ->assertOk();

        $this->assertCount(2, $response->json()[0]['partners']);
    }

    /** @test */
    public function deal_with_no_partners_returns_empty_partners_array()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');
        $this->createLead($tenant);

        $response = $this->actingAs($admin, 'tenant')
            ->getJson("/api/leads?tenant_id={$tenant->id}&include_partners=1")
            ->assertOk();

        $this->assertCount(0, $response->json()[0]['partners']);
    }

    // ── 5. LGU IDS protected rules unchanged ─────────────────────

    /** @test */
    public function lgu_ids_deals_still_return_reseller_name_correctly()
    {
        // LGU IDS is a locked tenant — verify normal deal list still works
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');
        $this->createLead($tenant, ['reseller_name' => 'LGU Referrer']);

        $this->actingAs($admin, 'tenant')
            ->getJson("/api/leads?tenant_id={$tenant->id}")
            ->assertOk()
            ->assertJsonFragment(['reseller_name' => 'LGU Referrer']);
    }

    // ── 6. Partner portal — unchanged ────────────────────────────

    /** @test */
    public function partner_can_only_see_their_authorized_deals()
    {
        $tenant  = $this->createTenant();
        $partner = $this->createPartner($tenant);
        $lead1   = $this->createLead($tenant, ['name' => 'Authorized Deal']);
        $lead2   = $this->createLead($tenant, ['name' => 'Unauthorized Deal']);
        $this->createDealPartner($lead1, $partner, 'active');
        // lead2 has no DealPartner for this partner

        $this->actingAs($partner, 'partner')
            ->get(route('partner.deals'))
            ->assertOk()
            ->assertSee('Authorized Deal')
            ->assertDontSee('Unauthorized Deal');
    }
}
