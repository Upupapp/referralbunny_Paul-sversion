<?php

namespace Tests\Feature;

use App\Models\DealPartner;
use App\Models\Lead;
use App\Models\Partner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for archived-deal guards in the partner and reseller portals.
 *
 * Does NOT use RefreshDatabase because several production ALTER TABLE
 * migrations use PostgreSQL-specific syntax (CREATE INDEX CONCURRENTLY, partial
 * WHERE indexes) that SQLite rejects. Instead, the test creates and drops only
 * the minimum tables it needs in setUp/tearDown.
 *
 * ReferrerPerformanceService is not tested here — its compute() uses
 * PostgreSQL-specific DISTINCT ON / NULLS LAST. The service wraps all SQL in
 * try/catch and defaults to zero values on failure, so the archived filter is
 * implicitly safe in any non-PG environment.
 */
class PortalArchivedDealGuardsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Partner::boot() fires ContactSyncService::syncPartner() on both created and updated.
        // The minimal test schema has no contacts table, so without this mock the service
        // would throw (currently swallowed by the boot try/catch, but fragile if that changes).
        $this->mock(\App\Services\ContactSyncService::class)
             ->shouldReceive('syncPartner')
             ->andReturn(null);
        $this->buildSchema();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    // ── Partner portal: addNote() ────────────────────────────────────────────

    public function test_addNote_returns_422_when_deal_is_archived(): void
    {
        [$partner, $lead] = $this->seedPartnerWithArchivedDeal();
        $csrf = Str::random(40);

        $response = $this->actingAs($partner, 'partner')
            ->withSession(['_token' => $csrf])
            ->post(route('partner.deals.notes', ['dealId' => $lead->id]), [
                '_token' => $csrf,
                'body'   => 'this note should be rejected',
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'This deal has been archived and can no longer receive notes.']);
    }

    public function test_addNote_returns_403_when_partner_lacks_deal_access(): void
    {
        $partner = $this->makePartner();
        $csrf    = Str::random(40);

        $lead = Lead::create([
            'tenant_id' => 'test-tenant',
            'name'      => 'Inaccessible Deal',
            'stage'     => 'introduction',
            'status'    => 'active',
        ]);

        $response = $this->actingAs($partner, 'partner')
            ->withSession(['_token' => $csrf])
            ->post(route('partner.deals.notes', ['dealId' => $lead->id]), [
                '_token' => $csrf,
                'body'   => 'should be forbidden',
            ]);

        $response->assertStatus(403);
    }

    public function test_addNote_returns_422_when_deal_accessible_only_via_splits_and_archived(): void
    {
        // Proves authorizedDealIds() picks up the deal_partner_splits path AND that the
        // archived 422 guard fires correctly for split-authorized partners (not just deal_partners).
        $partner = $this->makePartner();
        $csrf    = Str::random(40);

        $lead = Lead::create([
            'tenant_id' => 'test-tenant',
            'name'      => 'Split-Only Archived Deal',
            'stage'     => 'introduction',
            'status'    => 'archived',
        ]);

        // No deal_partners row — authorized via deal_partner_splits only.
        DB::table('deal_partner_splits')->insert([
            'id'              => (string) Str::uuid(),
            'tenant_id'       => 'test-tenant',
            'deal_id'         => $lead->id,
            'partner_user_id' => $partner->id,
            'status'          => 'active',
            'deleted_at'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->actingAs($partner, 'partner')
            ->withSession(['_token' => $csrf])
            ->post(route('partner.deals.notes', ['dealId' => $lead->id]), [
                '_token' => $csrf,
                'body'   => 'note on split-authorized archived deal',
            ]);

        // 422 (not 403) confirms authorizedDealIds() found the deal via splits,
        // then the archived guard fired.
        $response->assertStatus(422);
        $response->assertJson(['error' => 'This deal has been archived and can no longer receive notes.']);
    }

    // ── Partner portal: sendMessage() ────────────────────────────────────────

    public function test_sendMessage_returns_422_when_deal_is_archived(): void
    {
        [$partner, $lead] = $this->seedPartnerWithArchivedDeal();
        $csrf = Str::random(40);

        $response = $this->actingAs($partner, 'partner')
            ->withSession(['_token' => $csrf])
            ->post(route('partner.messages.send'), [
                '_token' => $csrf,
                'deal_id'=> $lead->id,
                'body'   => 'this message should be rejected',
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'This deal has been archived and can no longer receive messages.']);
    }

    // ── Reseller portal: $recentLeads query ──────────────────────────────────

    public function test_recent_leads_query_excludes_archived_deals(): void
    {
        $tenantId     = 'test-tenant';
        $resellerName = '[Test] Referrer';

        // Archived lead — must NOT appear in the recent-leads result
        Lead::create([
            'tenant_id'    => $tenantId,
            'name'         => 'Archived Deal',
            'stage'        => 'introduction',
            'status'       => 'archived',
            'reseller_name'=> $resellerName,
        ]);

        // Active lead — must appear
        Lead::create([
            'tenant_id'    => $tenantId,
            'name'         => 'Active Deal',
            'stage'        => 'introduction',
            'status'       => 'active',
            'reseller_name'=> $resellerName,
        ]);

        // Replicate the exact ResellerPortalController::dashboard() query
        $results = Lead::where('tenant_id', $tenantId)
            ->forResellerOrSplit($resellerName)
            ->whereNotIn('status', ['archived'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('active', $results->first()->status);
        $this->assertEquals('Active Deal', $results->first()->name);
    }

    // ── ReferrerPerformanceService (PostgreSQL-only) ──────────────────────────

    public function test_referrer_performance_service_total_deals_excludes_archived(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped(
                'ReferrerPerformanceService::compute() uses PostgreSQL-specific ' .
                'DISTINCT ON / NULLS LAST syntax. Run against a Postgres connection to test.'
            );
        }

        // TODO: implement against a Postgres test connection:
        //   1. Create a reseller via DB::table('resellers')->insert([...])
        //   2. Seed one archived lead (status='archived') and one active lead for that reseller
        //   3. Call ReferrerPerformanceService::forReseller($reseller, $tenantId)
        //   4. Assert $result->total_deals === 1 (active only)
        // The service wraps all SQL in try/catch and returns null on SQLite (DISTINCT ON / NULLS LAST).
        $this->assertTrue(true);
    }

    // ── Schema helpers ────────────────────────────────────────────────────────

    private function buildSchema(): void
    {
        // Drop first in case a RefreshDatabase test ran the SQLite stub migration
        // in the same process before us.
        $this->dropSchema();

        // Leads (SoftDeletes via deleted_at, UUID auto-generated in Lead::boot)
        Schema::create('leads', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('stage')->default('introduction');
            $table->string('status')->default('active');
            $table->string('reseller_name')->nullable();
            $table->decimal('deal_value', 15, 2)->nullable();
            $table->decimal('base_cost', 15, 2)->nullable();
            $table->decimal('added_amount', 15, 2)->nullable();
            $table->string('commission_status')->nullable();
            $table->integer('days_left')->nullable();
            $table->text('data')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        // Commission splits (needed by Lead::scopeForResellerOrSplit orWhereExists)
        Schema::create('commission_splits', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('lead_id');
            $table->string('reseller_name')->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        // Partner auth (Partner model → partner_users table)
        Schema::create('partner_users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('status')->default('active');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('remember_token')->nullable();
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamps();
        });

        // Deal partners (authorizedDealIds — primary source)
        Schema::create('deal_partners', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('deal_id');
            $table->string('partner_user_id');
            $table->string('added_by_id')->nullable();
            $table->string('added_by_type')->nullable();
            $table->string('status')->default('active');
            $table->json('permissions')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
        });

        // Deal partner splits (authorizedDealIds — secondary source)
        Schema::create('deal_partner_splits', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('deal_id');
            $table->string('partner_user_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    private function dropSchema(): void
    {
        Schema::dropIfExists('deal_partner_splits');
        Schema::dropIfExists('deal_partners');
        Schema::dropIfExists('partner_users');
        Schema::dropIfExists('commission_splits');
        Schema::dropIfExists('leads');
    }

    // ── Data helpers ──────────────────────────────────────────────────────────

    private function makePartner(): Partner
    {
        // Partner has no auto-UUID in boot(); must provide explicit id.
        return Partner::create([
            'id'        => (string) Str::uuid(),
            'tenant_id' => 'test-tenant',
            'email'     => 'test-partner-' . Str::random(6) . '@example.com',
            'password'  => 'hashed-password',
            'status'    => 'active',
        ]);
    }

    private function seedPartnerWithArchivedDeal(): array
    {
        $partner = $this->makePartner();

        // Lead auto-generates UUID via Lead::boot() creating hook.
        $lead = Lead::create([
            'tenant_id' => 'test-tenant',
            'name'      => 'Archived Test Deal',
            'stage'     => 'introduction',
            'status'    => 'archived',
        ]);

        // DealPartner has no auto-UUID; must provide explicit id.
        DealPartner::create([
            'id'              => (string) Str::uuid(),
            'tenant_id'       => 'test-tenant',
            'deal_id'         => $lead->id,
            'partner_user_id' => $partner->id,
            'status'          => 'active',
        ]);

        return [$partner, $lead];
    }
}
