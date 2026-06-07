<?php

namespace Tests\Feature;

use App\Models\DealAssignmentExtensionRequest;
use App\Models\DealExtensionRequestBatch;
use App\Services\BulkDealExtensionService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Mockery;
use Tests\TestCase;

/**
 * Tests admin approve / decline / skip endpoints for bulk extension requests.
 *
 * The DB is not migrated here; all service calls are mocked so tests run fast
 * and without PostgreSQL-specific migration concerns.
 */
class BulkExtensionAdminReviewTest extends TestCase
{
    private BulkDealExtensionService $service;
    private string $tenantId = 'tenant-abc';
    private string $batchId  = 'batch-001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = Mockery::mock(BulkDealExtensionService::class);
        $this->app->instance(BulkDealExtensionService::class, $this->service);

        // Stub TenantContext to return a fixed tenant
        $this->app->bind(TenantContext::class, function () {
            $mock = Mockery::mock(TenantContext::class);
            $mock->shouldReceive('id')->andReturn($this->tenantId);
            return $mock;
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        // Reset static role cache to prevent cross-test leakage (EnforcesAdminRole trait)
        $ref = new \ReflectionProperty(\App\Http\Traits\EnforcesAdminRole::class, 'roleCache');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
        // Reset TenantContext static state
        \App\Services\TenantContext::clear();
        parent::tearDown();
    }

    // ── Approve All ────────────────────────────────────────────────

    public function test_approve_all_returns_success_for_authenticated_admin(): void
    {
        $this->mockAdminAuth();

        $this->service
            ->shouldReceive('approveAll')
            ->once()
            ->with($this->batchId, $this->tenantId, Mockery::any(), 14, null)
            ->andReturn(['approved' => [new DealAssignmentExtensionRequest()], 'failed' => []]);

        $response = $this->postJson("/api/extension-requests/batches/{$this->batchId}/approve-all", [
            'approved_days' => 14,
        ]);

        $response->assertOk()->assertJsonFragment(['success' => true]);
    }

    public function test_approve_all_requires_approved_days(): void
    {
        $this->mockAdminAuth();

        $response = $this->postJson("/api/extension-requests/batches/{$this->batchId}/approve-all", []);

        $response->assertStatus(422)->assertJsonValidationErrors(['approved_days']);
    }

    public function test_approve_all_rejects_days_above_90(): void
    {
        $this->mockAdminAuth();

        $response = $this->postJson("/api/extension-requests/batches/{$this->batchId}/approve-all", [
            'approved_days' => 91,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['approved_days']);
    }

    // ── Decline All ────────────────────────────────────────────────

    public function test_decline_all_returns_success(): void
    {
        $this->mockAdminAuth();

        $this->service
            ->shouldReceive('declineAll')
            ->once()
            ->with($this->batchId, $this->tenantId, Mockery::any(), 'Budget cut this quarter')
            ->andReturn(['declined' => [new DealAssignmentExtensionRequest()], 'failed' => []]);

        $response = $this->postJson("/api/extension-requests/batches/{$this->batchId}/decline-all", [
            'reviewer_note' => 'Budget cut this quarter',
        ]);

        $response->assertOk()->assertJsonFragment(['success' => true]);
    }

    public function test_decline_all_requires_reviewer_note(): void
    {
        $this->mockAdminAuth();

        $response = $this->postJson("/api/extension-requests/batches/{$this->batchId}/decline-all", []);

        $response->assertStatus(422)->assertJsonValidationErrors(['reviewer_note']);
    }

    // ── Skip All ──────────────────────────────────────────────────

    public function test_skip_all_returns_success(): void
    {
        $this->mockAdminAuth();

        $this->service
            ->shouldReceive('skipAll')
            ->once()
            ->andReturn(['skipped' => [new DealAssignmentExtensionRequest()], 'failed' => []]);

        $response = $this->postJson("/api/extension-requests/batches/{$this->batchId}/skip-all", []);

        $response->assertOk()->assertJsonFragment(['success' => true]);
    }

    // ── Approve Selected ──────────────────────────────────────────

    public function test_approve_selected_returns_success(): void
    {
        $this->mockAdminAuth();
        $requestIds = ['uuid-1', 'uuid-2'];

        $this->service
            ->shouldReceive('approveSelected')
            ->once()
            ->with($this->batchId, $this->tenantId, Mockery::any(), $requestIds, 7, null)
            ->andReturn(['approved' => array_fill(0, 2, new DealAssignmentExtensionRequest()), 'failed' => []]);

        $response = $this->postJson("/api/extension-requests/batches/{$this->batchId}/approve-selected", [
            'request_ids'   => $requestIds,
            'approved_days' => 7,
        ]);

        $response->assertOk()->assertJsonFragment(['processed' => 2]);
    }

    // ── Per-item Approve ──────────────────────────────────────────

    public function test_per_item_approve_updates_days_left(): void
    {
        $this->mockAdminAuth();
        $requestId = 'req-001';
        $result    = new DealAssignmentExtensionRequest(['approved_days' => 10]);

        $this->service
            ->shouldReceive('approveItem')
            ->once()
            ->with($requestId, $this->tenantId, Mockery::any(), 10, null)
            ->andReturn($result);

        $response = $this->postJson("/api/bulk-extension-requests/{$requestId}/approve", [
            'approved_days' => 10,
        ]);

        $response->assertOk()->assertJsonFragment(['success' => true]);
    }

    // ── Authorization ─────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson("/api/extension-requests/batches/{$this->batchId}/approve-all", [
            'approved_days' => 7,
        ]);

        $response->assertStatus(401);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function mockAdminAuth(): void
    {
        $user = Mockery::mock(\App\Models\TenantUser::class)->makePartial();
        $user->id = 'admin-user-1';

        $this->actingAs($user, 'tenant');

        // Mock the membership lookup used by isAdminOrManager() in the trait
        \Illuminate\Support\Facades\DB::shouldReceive('table')->passthru()->byDefault();
        \App\Models\TenantMembership::unsetEventDispatcher();
    }
}
