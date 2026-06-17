<?php

namespace Tests\Feature;

use App\Models\CommissionSplit;
use App\Models\Lead;
use App\Models\Reseller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for co-referrer CRUD: addReferrer, updateCoReferrerSplit, removeCoReferrer.
 *
 * Does NOT use RefreshDatabase — same SQLite/PG incompatibility that affects the rest of the
 * suite. Builds and tears down only the tables it needs.
 *
 * addReferrer tests are skipped on SQLite because addReferrer() runs a raw
 * TRIM(CONCAT(...)) query against tenant_users that is PG/MySQL-only syntax.
 * Update and remove tests are SQLite-safe and run in all environments.
 */
class CoReferrerTest extends TestCase
{
    private const TENANT = 'test-tenant';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    // ── addReferrer (PostgreSQL-only) ─────────────────────────────────────────

    public function test_non_primary_cannot_add_co_referrer(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('addReferrer uses TRIM(CONCAT(...)) — PostgreSQL/MySQL only.');
        }

        $primary   = $this->makeReseller('Primary Referrer', 'primary@example.com');
        $secondary = $this->makeReseller('Other Referrer', 'other@example.com');
        $target    = $this->makeReseller('Target Referrer', 'target@example.com');
        $lead      = $this->makeLead($primary->name);

        $response = $this->actingAs($secondary, 'reseller')
            ->postJson("/reseller/" . self::TENANT . "/deals/{$lead->id}/referrers", [
                'referrer_email' => $target->email,
                'percentage'     => 10,
            ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Only the primary Referrer on this deal can add co-referrers.']);
    }

    public function test_locked_commission_blocks_add(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('addReferrer uses TRIM(CONCAT(...)) — PostgreSQL/MySQL only.');
        }

        $primary = $this->makeReseller('Primary Referrer', 'primary@example.com');
        $target  = $this->makeReseller('Target Referrer', 'target@example.com');
        $lead    = $this->makeLead($primary->name, commissionStatus: 'locked');

        $response = $this->actingAs($primary, 'reseller')
            ->postJson("/reseller/" . self::TENANT . "/deals/{$lead->id}/referrers", [
                'referrer_email' => $target->email,
                'percentage'     => 10,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Commission splits cannot be modified after commission is locked or paid.']);
    }

    public function test_cap_enforced_on_add(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('addReferrer uses TRIM(CONCAT(...)) — PostgreSQL/MySQL only.');
        }

        $primary = $this->makeReseller('Primary Referrer', 'primary@example.com');
        $target  = $this->makeReseller('Target Referrer', 'target@example.com');
        $lead    = $this->makeLead($primary->name);
        $this->makeSplit($lead->id, 'Existing Co-Ref', 99.0);

        $response = $this->actingAs($primary, 'reseller')
            ->postJson("/reseller/" . self::TENANT . "/deals/{$lead->id}/referrers", [
                'referrer_email' => $target->email,
                'percentage'     => 2,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('cannot exceed 100%', strtolower($response->json('error') ?? ''));
    }

    // ── updateCoReferrerSplit ─────────────────────────────────────────────────

    public function test_primary_can_update_co_referrer_split(): void
    {
        $primary = $this->makeReseller('Primary Referrer', 'primary@example.com');
        $lead    = $this->makeLead($primary->name);
        $split   = $this->makeSplit($lead->id, 'Co-Ref One', 10.0);

        $response = $this->actingAs($primary, 'reseller')
            ->patchJson("/reseller/" . self::TENANT . "/deals/{$lead->id}/splits/{$split->id}", [
                'percentage' => 20,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'new_percentage' => 20.0, 'old_percentage' => 10.0]);
        $this->assertDatabaseHas('commission_splits', ['id' => $split->id, 'percentage' => 20.0]);
    }

    public function test_non_primary_cannot_update_co_referrer_split(): void
    {
        $primary   = $this->makeReseller('Primary Referrer', 'primary@example.com');
        $secondary = $this->makeReseller('Other Referrer', 'other@example.com');
        $lead      = $this->makeLead($primary->name);
        $split     = $this->makeSplit($lead->id, 'Co-Ref One', 10.0);

        $response = $this->actingAs($secondary, 'reseller')
            ->patchJson("/reseller/" . self::TENANT . "/deals/{$lead->id}/splits/{$split->id}", [
                'percentage' => 20,
            ]);

        $response->assertStatus(403);
    }

    public function test_locked_commission_blocks_update(): void
    {
        $primary = $this->makeReseller('Primary Referrer', 'primary@example.com');
        $lead    = $this->makeLead($primary->name, commissionStatus: 'locked');
        $split   = $this->makeSplit($lead->id, 'Co-Ref One', 10.0);

        $response = $this->actingAs($primary, 'reseller')
            ->patchJson("/reseller/" . self::TENANT . "/deals/{$lead->id}/splits/{$split->id}", [
                'percentage' => 20,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Commission splits cannot be modified after commission is locked or paid.']);
    }

    public function test_update_cap_blocks_exceeding_100_percent(): void
    {
        $primary = $this->makeReseller('Primary Referrer', 'primary@example.com');
        $lead    = $this->makeLead($primary->name);
        $split1  = $this->makeSplit($lead->id, 'Co-Ref A', 60.0);
        $split2  = $this->makeSplit($lead->id, 'Co-Ref B', 30.0);

        // Try to raise split2 from 30% to 50% — would make total 110%
        $response = $this->actingAs($primary, 'reseller')
            ->patchJson("/reseller/" . self::TENANT . "/deals/{$lead->id}/splits/{$split2->id}", [
                'percentage' => 50,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('percentage');
    }

    // ── removeCoReferrer ──────────────────────────────────────────────────────

    public function test_primary_can_remove_co_referrer(): void
    {
        $primary = $this->makeReseller('Primary Referrer', 'primary@example.com');
        $lead    = $this->makeLead($primary->name);
        $split   = $this->makeSplit($lead->id, 'Co-Ref One', 10.0);

        $response = $this->actingAs($primary, 'reseller')
            ->deleteJson("/reseller/" . self::TENANT . "/deals/{$lead->id}/splits/{$split->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertSoftDeleted('commission_splits', ['id' => $split->id]);
    }

    public function test_non_primary_cannot_remove_co_referrer(): void
    {
        $primary   = $this->makeReseller('Primary Referrer', 'primary@example.com');
        $secondary = $this->makeReseller('Other Referrer', 'other@example.com');
        $lead      = $this->makeLead($primary->name);
        $split     = $this->makeSplit($lead->id, 'Co-Ref One', 10.0);

        $response = $this->actingAs($secondary, 'reseller')
            ->deleteJson("/reseller/" . self::TENANT . "/deals/{$lead->id}/splits/{$split->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('commission_splits', ['id' => $split->id, 'deleted_at' => null]);
    }

    public function test_locked_commission_blocks_remove(): void
    {
        $primary = $this->makeReseller('Primary Referrer', 'primary@example.com');
        $lead    = $this->makeLead($primary->name, commissionStatus: 'paid');
        $split   = $this->makeSplit($lead->id, 'Co-Ref One', 10.0);

        $response = $this->actingAs($primary, 'reseller')
            ->deleteJson("/reseller/" . self::TENANT . "/deals/{$lead->id}/splits/{$split->id}");

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Commission splits cannot be modified after commission is locked or paid.']);
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    private function buildSchema(): void
    {
        $this->dropSchema();

        Schema::create('resellers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->string('password')->nullable();
            $table->string('remember_token')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('stage')->default('introduction');
            $table->string('status')->default('active');
            $table->string('reseller_name')->nullable();
            $table->string('commission_status')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('commission_splits', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('lead_id');
            $table->string('reseller_name')->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->string('role')->default('secondary');
            $table->string('activity_status')->nullable();
            $table->timestamp('deleted_at')->nullable();
            // CommissionSplit has $timestamps = false — no created_at/updated_at columns
        });
    }

    private function dropSchema(): void
    {
        Schema::dropIfExists('commission_splits');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('resellers');
    }

    // ── Data helpers ──────────────────────────────────────────────────────────

    private function makeReseller(string $name, string $email): Reseller
    {
        return Reseller::create([
            'tenant_id' => self::TENANT,
            'name'      => $name,
            'email'     => $email,
            'status'    => 'active',
            'password'  => bcrypt('password'),
        ]);
    }

    private function makeLead(string $resellerName, ?string $commissionStatus = null): Lead
    {
        return Lead::create([
            'tenant_id'         => self::TENANT,
            'name'              => 'Test Deal',
            'stage'             => 'introduction',
            'status'            => 'active',
            'reseller_name'     => $resellerName,
            'commission_status' => $commissionStatus,
        ]);
    }

    private function makeSplit(string $leadId, string $resellerName, float $percentage): CommissionSplit
    {
        // CommissionSplit has no auto-UUID boot(); id must be supplied explicitly via forceCreate().
        return CommissionSplit::forceCreate([
            'id'              => (string) Str::uuid(),
            'lead_id'         => $leadId,
            'reseller_name'   => $resellerName,
            'percentage'      => $percentage,
            'role'            => 'secondary',
            'activity_status' => 'active',
        ]);
    }
}
