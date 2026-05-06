<?php
namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    // Tests use real DB calls with tenant_id scoping

    public function test_lead_index_requires_authenticated_tenant_context(): void
    {
        $response = $this->getJson('/api/leads');
        $response->assertUnauthorized(); // 401
    }

    public function test_tenant_a_admin_cannot_see_tenant_b_leads_via_query_param(): void
    {
        // If an authenticated Tenant A user submits tenant_id=B, they should
        // only see Tenant A data (TenantContext overrides the param)
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = TenantUser::factory()->forTenant($tenantA)->create();
        $leadB = Lead::factory()->forTenant($tenantB)->create();

        $this->actingAs($userA, 'tenant')
            ->getJson("/api/leads?tenant_id={$tenantB->id}")
            ->assertJsonMissing(['id' => $leadB->id]);
    }

    public function test_reseller_cannot_see_another_tenants_leads(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $resellerA = Reseller::factory()->forTenant($tenantA)->create();
        $leadB = Lead::factory()->forTenant($tenantB)->create();

        $this->actingAs($resellerA, 'reseller')
            ->getJson("/api/leads?tenant_id={$tenantB->id}")
            ->assertJsonMissing(['id' => $leadB->id]);
    }

    public function test_lead_show_returns_404_for_wrong_tenant_lead(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = TenantUser::factory()->forTenant($tenantA)->create();
        $leadB = Lead::factory()->forTenant($tenantB)->create();

        $this->actingAs($userA, 'tenant')
            ->getJson("/api/leads/{$leadB->id}")
            ->assertNotFound();
    }

    public function test_messages_require_tenant_context(): void
    {
        $response = $this->getJson('/api/messages');
        $response->assertUnauthorized();
    }
}
