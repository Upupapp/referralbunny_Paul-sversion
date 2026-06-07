<?php

namespace Tests\Feature;

use App\Services\BulkDealExtensionService;
use App\Services\DealExtensionEligibilityService;
use App\Services\TenantContext;
use Mockery;
use Tests\TestCase;

/**
 * Verifies that cross-tenant access is rejected for bulk extension endpoints.
 */
class BulkExtensionTenantIsolationTest extends TestCase
{
    private BulkDealExtensionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = Mockery::mock(BulkDealExtensionService::class);
        $this->app->instance(BulkDealExtensionService::class, $this->service);

        // Mock eligibility service so the controller can be resolved via app() in the
        // reflection test without triggering DB-dependent constructor dependencies.
        $this->app->instance(
            DealExtensionEligibilityService::class,
            Mockery::mock(DealExtensionEligibilityService::class)
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $ref = new \ReflectionProperty(\App\Http\Traits\EnforcesAdminRole::class, 'roleCache');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
        \App\Services\TenantContext::clear();
        parent::tearDown();
    }

    public function test_request_without_tenant_context_returns_403_on_approve_all(): void
    {
        // No tenant context bound → isAdminOrManager returns false (no tenantId)
        $this->app->bind(TenantContext::class, function () {
            $mock = Mockery::mock(TenantContext::class);
            $mock->shouldReceive('id')->andReturn(null);
            return $mock;
        });

        $user = Mockery::mock(\App\Models\TenantUser::class)->makePartial();
        $user->id = 'user-1';
        $this->actingAs($user, 'tenant');

        $response = $this->postJson('/api/extension-requests/batches/batch-1/approve-all', [
            'approved_days' => 7,
        ]);

        $response->assertStatus(403);
    }

    public function test_request_ids_are_scoped_to_batch_and_tenant(): void
    {
        // Service enforces scoping — passing foreign IDs must be filtered out
        $tenantId  = 'tenant-a';
        $batchId   = 'batch-a';
        $foreignId = 'foreign-request-from-tenant-b';

        $this->app->bind(TenantContext::class, function () use ($tenantId) {
            $mock = Mockery::mock(TenantContext::class);
            $mock->shouldReceive('id')->andReturn($tenantId);
            return $mock;
        });

        // Service should receive the foreign IDs — internally it re-scopes them
        // and returns an empty approved set (nothing matched)
        $this->service
            ->shouldReceive('approveSelected')
            ->once()
            ->with($batchId, $tenantId, Mockery::any(), [$foreignId], 7, null)
            ->andReturn(['approved' => [], 'failed' => []]);

        $user = Mockery::mock(\App\Models\TenantUser::class)->makePartial();
        $user->id = 'admin-1';
        $this->actingAs($user, 'tenant');

        $response = $this->postJson("/api/extension-requests/batches/{$batchId}/approve-selected", [
            'request_ids'   => [$foreignId],
            'approved_days' => 7,
        ]);

        // Request reaches the service which silently drops the foreign IDs
        $response->assertOk()->assertJsonFragment(['processed' => 0]);
    }

    public function test_eligible_deals_endpoint_accessible_via_reseller_session(): void
    {
        $tenantId = 'tenant-a';

        $this->app->bind(TenantContext::class, function () use ($tenantId) {
            $mock = Mockery::mock(TenantContext::class);
            $mock->shouldReceive('id')->andReturn($tenantId);
            return $mock;
        });

        $reseller = Mockery::mock(\App\Models\Reseller::class)->makePartial();
        $reseller->id        = 'reseller-1';
        $reseller->tenant_id = $tenantId;
        $reseller->name      = 'Test Referrer';

        $this->app->instance(\App\Services\DealExtensionEligibilityService::class,
            Mockery::mock(\App\Services\DealExtensionEligibilityService::class, function ($m) {
                $m->shouldReceive('getEligibleDealsForReseller')->andReturn(collect());
            })
        );

        $this->actingAs($reseller, 'reseller');

        // This route is now in the reseller-auth group (Fix #2)
        $response = $this->getJson('/api/extension-requests/eligible-deals');

        // Should resolve to 200 (not 401) now that the reseller-auth group exists
        $response->assertOk();
    }

    public function test_referrer_cannot_access_another_referrers_batch(): void
    {
        $tenantId  = 'tenant-a';
        $batchId   = 'batch-001';

        $this->app->bind(TenantContext::class, function () use ($tenantId) {
            $mock = Mockery::mock(TenantContext::class);
            $mock->shouldReceive('id')->andReturn($tenantId);
            return $mock;
        });

        $foreignBatch        = new \App\Models\DealExtensionRequestBatch();
        $foreignBatch->id    = $batchId;
        $foreignBatch->requested_by_reseller_id = 'different-reseller';

        $this->service
            ->shouldReceive('getBatchWithItems')
            ->with($batchId, $tenantId)
            ->andReturn($foreignBatch);

        $reseller = Mockery::mock(\App\Models\Reseller::class)->makePartial();
        $reseller->id        = 'reseller-1';
        $reseller->tenant_id = $tenantId;

        // Mock the Reseller lookup inside the controller
        \App\Models\Reseller::unguard();

        $this->actingAs($reseller, 'reseller');

        $response = $this->getJson("/api/extension-requests/batches/{$batchId}");

        $response->assertStatus(403);
    }

    /**
     * Regression for Fix #7:
     * Super Admin (web guard only) should receive a clear 403 when accessing the
     * Referrer portal. EnsureResellerAccess middleware intercepts them first and
     * issues a redirect to the tenant dashboard — the controller is never reached.
     */
    public function test_super_admin_is_redirected_away_from_reseller_index(): void
    {
        $tenantId = 'tenant-a';

        $this->app->bind(TenantContext::class, function () use ($tenantId) {
            $mock = Mockery::mock(TenantContext::class);
            $mock->shouldReceive('id')->andReturn($tenantId);
            return $mock;
        });

        $superAdmin     = Mockery::mock(\App\Models\User::class)->makePartial();
        $superAdmin->id = 999;
        $this->actingAs($superAdmin, 'web');

        $response = $this->get("/reseller/{$tenantId}/extension-requests");

        // EnsureResellerAccess middleware redirects web-guard-only Super Admins
        $response->assertStatus(302);
        $response->assertRedirect();
    }

    /**
     * Regression for Fix #7 — belt-and-suspenders:
     * resolveReseller() in the web controller aborts with a clear 403 message
     * if EnsureResellerAccess is bypassed (e.g., missing from route group).
     * Tested by invoking the private method directly via reflection.
     */
    public function test_resolve_reseller_throws_403_for_web_guard_only_user(): void
    {
        $controller = app(\App\Http\Controllers\BulkDealExtensionWebController::class);
        $method     = new \ReflectionMethod($controller, 'resolveReseller');
        $method->setAccessible(true);

        $superAdmin     = Mockery::mock(\App\Models\User::class)->makePartial();
        $superAdmin->id = 999;
        $this->actingAs($superAdmin, 'web');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $method->invoke($controller, 'tenant-a');
    }
}
