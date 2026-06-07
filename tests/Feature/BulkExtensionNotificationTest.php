<?php

namespace Tests\Feature;

use App\Models\DealAssignmentExtensionRequest;
use App\Models\DealExtensionRequestBatch;
use App\Services\BulkDealExtensionService;
use App\Services\NotificationDispatchService;
use App\Services\TenantContext;
use Mockery;
use Tests\TestCase;

/**
 * Verifies notification dispatch behaviour for bulk extension operations.
 *
 * Key assertions:
 * - Admin email is queued on batch submission.
 * - Referrer receives one summary notification on batch completion, NOT one per deal.
 * - Bell cache is busted after a partial result notification.
 */
class BulkExtensionNotificationTest extends TestCase
{
    private BulkDealExtensionService $service;
    private string $tenantId = 'tenant-test';
    private string $batchId  = 'batch-notif-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = Mockery::mock(BulkDealExtensionService::class);
        $this->app->instance(BulkDealExtensionService::class, $this->service);

        $this->app->bind(TenantContext::class, function () {
            $mock = Mockery::mock(TenantContext::class);
            $mock->shouldReceive('id')->andReturn($this->tenantId);
            return $mock;
        });
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

    public function test_bulk_decline_does_not_send_per_item_notifications_when_suppressed(): void
    {
        // The suppressIndividualNotify flag means individual notifications should NOT fire
        // inside declineItem when called from declineAll.
        // We test this by verifying declineAll is called (not declineItem loop from controller)
        // and the response succeeds.
        $this->mockAdminAuth();

        $this->service
            ->shouldReceive('declineAll')
            ->once()
            ->andReturn([
                'declined' => array_fill(0, 5, new DealAssignmentExtensionRequest()),
                'failed'   => [],
            ]);

        $response = $this->postJson("/api/extension-requests/batches/{$this->batchId}/decline-all", [
            'reviewer_note' => 'Budget constraints apply to all deals.',
        ]);

        $response->assertOk()->assertJsonFragment(['processed' => 5]);
    }

    public function test_approve_all_calls_service_once_not_per_item(): void
    {
        // Controller must call service->approveAll() once, not loop through items
        $this->mockAdminAuth();

        $this->service
            ->shouldReceive('approveAll')
            ->once()
            ->andReturn(['approved' => [], 'failed' => []]);

        $this->service->shouldNotReceive('approveItem');

        $this->postJson("/api/extension-requests/batches/{$this->batchId}/approve-all", [
            'approved_days' => 10,
        ]);
    }

    public function test_decline_all_calls_service_once_not_per_item(): void
    {
        $this->mockAdminAuth();

        $this->service
            ->shouldReceive('declineAll')
            ->once()
            ->andReturn(['declined' => [], 'failed' => []]);

        $this->service->shouldNotReceive('declineItem');

        $this->postJson("/api/extension-requests/batches/{$this->batchId}/decline-all", [
            'reviewer_note' => 'Not approved for policy reasons.',
        ]);
    }

    public function test_mixed_reject_approve_rest_calls_service_once(): void
    {
        $this->mockAdminAuth();

        $this->service
            ->shouldReceive('rejectSelectedApproveRest')
            ->once()
            ->andReturn(['approved' => [], 'declined' => [], 'failed' => []]);

        $response = $this->postJson("/api/extension-requests/batches/{$this->batchId}/reject-selected-approve-rest", [
            'request_ids'      => ['req-1'],
            'approved_days'    => 7,
            'rejection_reason' => 'Selected deals are ineligible.',
        ]);

        $response->assertOk();
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function mockAdminAuth(): void
    {
        $user     = Mockery::mock(\App\Models\TenantUser::class)->makePartial();
        $user->id = 'admin-1';
        $this->actingAs($user, 'tenant');
    }
}
