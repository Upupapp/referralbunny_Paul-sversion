<?php

namespace Tests\Feature;

use App\Models\DealAssignmentExtensionRequest;
use App\Models\DealExtensionRequestBatch;
use App\Services\BulkDealExtensionService;
use App\Services\TenantContext;
use Mockery;
use Tests\TestCase;

/**
 * Verifies that the admin batch review Blade template renders the bulk action bar
 * correctly based on pending_count and skipped_count.
 *
 * Key regression covered by Fix #1: when pending_count = 0 and skipped_count > 0,
 * the bulk action bar must still be rendered so skipped items can be bulk-actioned.
 */
class BulkExtensionBladeTest extends TestCase
{
    private BulkDealExtensionService $service;
    private string $tenantId = 'tenant-blade-test';

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

    /**
     * Regression for Finding #1:
     * Bulk action bar must render when pending_count=0 and skipped_count>0.
     */
    public function test_bulk_action_bar_renders_when_only_skipped_items_remain(): void
    {
        $batch = $this->makeBatch(pendingCount: 0, skippedCount: 2);

        $this->service->shouldReceive('getBatchWithItems')
            ->andReturn($batch);

        $this->mockAdminAuth();

        $response = $this->get("/tenant/{$this->tenantId}/extension-requests/{$batch->id}");

        $response->assertOk();
        // The bulk action bar section is wrapped in @if($hasPending)
        // With the fix, $hasPending = (0 + 2) > 0 = true, so the section renders.
        $response->assertSee('Bulk actions');
        $response->assertSee('Approve All');
        $response->assertSee('Skip All');
    }

    /**
     * Bulk action bar must NOT render when both pending_count and skipped_count are zero.
     */
    public function test_bulk_action_bar_hidden_when_all_items_resolved(): void
    {
        $batch = $this->makeBatch(pendingCount: 0, skippedCount: 0, approvedCount: 3);

        $this->service->shouldReceive('getBatchWithItems')
            ->andReturn($batch);

        $this->mockAdminAuth();

        $response = $this->get("/tenant/{$this->tenantId}/extension-requests/{$batch->id}");

        $response->assertOk();
        $response->assertDontSee('Bulk actions');
    }

    /**
     * Bulk action bar renders when there are pending items (normal case).
     */
    public function test_bulk_action_bar_renders_when_pending_items_exist(): void
    {
        $batch = $this->makeBatch(pendingCount: 3, skippedCount: 0);

        $this->service->shouldReceive('getBatchWithItems')
            ->andReturn($batch);

        $this->mockAdminAuth();

        $response = $this->get("/tenant/{$this->tenantId}/extension-requests/{$batch->id}");

        $response->assertOk();
        $response->assertSee('Bulk actions');
    }

    /**
     * pendingCount Alpine variable includes both pending and skipped items.
     */
    public function test_alpine_pending_count_includes_skipped(): void
    {
        $batch = $this->makeBatch(pendingCount: 2, skippedCount: 3);

        $this->service->shouldReceive('getBatchWithItems')
            ->andReturn($batch);

        $this->mockAdminAuth();

        $response = $this->get("/tenant/{$this->tenantId}/extension-requests/{$batch->id}");

        $response->assertOk();
        // The rendered Alpine state should reflect 2 + 3 = 5
        $response->assertSee('pendingCount: 5', false);
    }

    /**
     * Select all button label updated to include skipped items.
     */
    public function test_select_all_button_mentions_skipped(): void
    {
        $batch = $this->makeBatch(pendingCount: 1, skippedCount: 1);

        $this->service->shouldReceive('getBatchWithItems')
            ->andReturn($batch);

        $this->mockAdminAuth();

        $response = $this->get("/tenant/{$this->tenantId}/extension-requests/{$batch->id}");

        $response->assertOk();
        $response->assertSee('pending &amp; skipped', false);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function makeBatch(
        int $pendingCount,
        int $skippedCount,
        int $approvedCount = 0,
        int $declinedCount = 0,
    ): DealExtensionRequestBatch {
        $batch = new DealExtensionRequestBatch();
        $batch->id                        = 'batch-' . uniqid();
        $batch->tenant_id                 = $this->tenantId;
        $batch->batch_reference           = 'BER-TESTREF1';
        $batch->status                    = $pendingCount + $skippedCount > 0 ? 'pending' : 'approved';
        $batch->shared_reason             = 'Need more time to close these deals.';
        $batch->requested_extension_days  = 14;
        $batch->total_items               = $pendingCount + $skippedCount + $approvedCount + $declinedCount;
        $batch->pending_count             = $pendingCount;
        $batch->skipped_count             = $skippedCount;
        $batch->approved_count            = $approvedCount;
        $batch->declined_count            = $declinedCount;
        $batch->submitted_at              = now();
        $batch->resolved_at               = null;
        $batch->last_decision_at          = null;
        $batch->exists                    = true;

        // Attach empty items collection (no per-item rendering needed for these tests)
        $batch->setRelation('items', collect());
        $batch->setRelation('requestedByReseller', null);

        return $batch;
    }

    private function mockAdminAuth(): void
    {
        $user     = Mockery::mock(\App\Models\TenantUser::class)->makePartial();
        $user->id = 'admin-1';
        $this->actingAs($user, 'tenant');
    }
}
