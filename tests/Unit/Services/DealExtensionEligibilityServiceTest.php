<?php

namespace Tests\Unit\Services;

use App\Models\DealAssignmentExtensionRequest;
use App\Models\Lead;
use App\Models\Reseller;
use App\Services\DealExtensionEligibilityService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for DealExtensionEligibilityService::checkForReseller.
 *
 * All DB calls are mocked so these run without a database.
 */
class DealExtensionEligibilityServiceTest extends TestCase
{
    private DealExtensionEligibilityService $svc;
    private Reseller $reseller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc      = new DealExtensionEligibilityService();
        $this->reseller = $this->makeReseller('tenant-1', 'John Doe');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_eligible_deal_returns_true(): void
    {
        $deal = $this->makeDeal('tenant-1', 'John Doe', 'active', 10);

        // Mock duplicate check
        $this->mockNoPendingRequest($deal->id, $this->reseller->id, $deal->tenant_id);

        $result = $this->svc->checkForReseller($deal, $this->reseller);

        $this->assertTrue($result['eligible']);
        $this->assertNull($result['reason']);
    }

    public function test_deal_from_different_tenant_is_ineligible(): void
    {
        $deal = $this->makeDeal('tenant-OTHER', 'John Doe', 'active', 10);

        $result = $this->svc->checkForReseller($deal, $this->reseller);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('another tenant', $result['reason']);
    }

    public function test_deal_not_assigned_to_reseller_is_ineligible(): void
    {
        $deal = $this->makeDeal('tenant-1', 'Other Person', 'active', 10);

        $result = $this->svc->checkForReseller($deal, $this->reseller);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('not assigned', $result['reason']);
    }

    public function test_archived_deal_is_ineligible(): void
    {
        $deal = $this->makeDeal('tenant-1', 'John Doe', 'archived', 10);

        $result = $this->svc->checkForReseller($deal, $this->reseller);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('archived', $result['reason']);
    }

    public function test_deal_with_locked_commission_is_ineligible(): void
    {
        $deal = $this->makeDeal('tenant-1', 'John Doe', 'active', 10, commissionStatus: 'locked');

        $result = $this->svc->checkForReseller($deal, $this->reseller);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('locked', $result['reason']);
    }

    public function test_deal_with_paid_commission_is_ineligible(): void
    {
        $deal = $this->makeDeal('tenant-1', 'John Doe', 'active', 10, commissionStatus: 'paid');

        $result = $this->svc->checkForReseller($deal, $this->reseller);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('paid', $result['reason']);
    }

    public function test_deal_with_no_days_left_is_ineligible(): void
    {
        $deal = $this->makeDeal('tenant-1', 'John Doe', 'active', daysLeft: null);

        $result = $this->svc->checkForReseller($deal, $this->reseller);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('no active deadline', $result['reason']);
    }

    public function test_deal_in_non_extendable_status_is_ineligible(): void
    {
        $deal = $this->makeDeal('tenant-1', 'John Doe', 'signed', 10);

        $result = $this->svc->checkForReseller($deal, $this->reseller);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('not eligible', $result['reason']);
    }

    public function test_deal_with_duplicate_pending_request_is_ineligible(): void
    {
        $deal = $this->makeDeal('tenant-1', 'John Doe', 'active', 5);

        // Simulate a pending request already existing
        $mockQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('exists')->andReturn(true);

        // Bind the model to return our mock
        DealAssignmentExtensionRequest::setConnectionResolver(
            $this->createConnectionWithExists(true)
        );

        $result = $this->svc->checkForReseller($deal, $this->reseller);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('pending extension request', $result['reason']);
    }

    public function test_check_many_returns_eligibility_for_each_deal(): void
    {
        $deal1 = $this->makeDeal('tenant-1', 'John Doe', 'active', 5);
        $deal2 = $this->makeDeal('tenant-1', 'John Doe', 'archived', 5);  // ineligible
        $deal3 = $this->makeDeal('tenant-1', 'Someone Else', 'active', 5); // ineligible

        DealAssignmentExtensionRequest::setConnectionResolver(
            $this->createConnectionWithExists(false)
        );

        $results = $this->svc->checkManyForReseller(collect([$deal1, $deal2, $deal3]), $this->reseller);

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]['eligible']);
        $this->assertFalse($results[1]['eligible']);
        $this->assertFalse($results[2]['eligible']);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function makeReseller(string $tenantId, string $name): Reseller
    {
        $r            = new Reseller();
        $r->id        = 'res-' . uniqid();
        $r->tenant_id = $tenantId;
        $r->name      = $name;
        return $r;
    }

    private function makeDeal(
        string  $tenantId,
        string  $resellerName,
        string  $status,
        ?int    $daysLeft = 10,
        string  $commissionStatus = 'pending'
    ): Lead {
        $lead                   = new Lead();
        $lead->id               = 'lead-' . uniqid();
        $lead->tenant_id        = $tenantId;
        $lead->reseller_name    = $resellerName;
        $lead->status           = $status;
        $lead->days_left        = $daysLeft;
        $lead->commission_status = $commissionStatus;
        $lead->deleted_at       = null;
        return $lead;
    }

    private function mockNoPendingRequest(string $dealId, string $resellerId, string $tenantId): void
    {
        DealAssignmentExtensionRequest::setConnectionResolver(
            $this->createConnectionWithExists(false)
        );
    }

    private function createConnectionWithExists(bool $existsResult): \Illuminate\Database\ConnectionResolverInterface
    {
        $connection = Mockery::mock(\Illuminate\Database\ConnectionInterface::class);
        $connection->shouldReceive('getQueryGrammar')->andReturn(
            Mockery::mock(\Illuminate\Database\Query\Grammars\Grammar::class)->makePartial()
        );
        $connection->shouldReceive('getPostProcessor')->andReturn(
            Mockery::mock(\Illuminate\Database\Query\Processors\Processor::class)->makePartial()
        );
        $connection->shouldReceive('select')->andReturn($existsResult ? [['aggregate' => 1]] : [['aggregate' => 0]]);
        $connection->shouldReceive('getName')->andReturn('sqlite');
        $connection->shouldReceive('getDatabaseName')->andReturn(':memory:');

        $resolver = Mockery::mock(\Illuminate\Database\ConnectionResolverInterface::class);
        $resolver->shouldReceive('connection')->andReturn($connection);

        return $resolver;
    }
}
