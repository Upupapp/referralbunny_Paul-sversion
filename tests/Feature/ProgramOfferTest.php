<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\ProgramOffer;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the Programs V4 "Offers" tab.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramOfferTest extends TestCase
{
    private const TENANT_ID = 'test-offers-tenant';

    private Tenant     $tenant;
    private TenantUser $ownerUser;
    private TenantUser $managerUser;
    private Program    $program;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('programs.enabled', true);
        $this->buildSchema();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    public function test_owner_can_create_offer_with_percentage_reward(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.offers.store', [self::TENANT_ID, $this->program->id]), [
                'name'            => 'Percentage Offer',
                'reward_model'    => 'percentage',
                'percentage_rate' => 10,
            ])
            ->assertRedirect();

        $offer = ProgramOffer::where('program_id', $this->program->id)->where('name', 'Percentage Offer')->first();

        $this->assertNotNull($offer);
        $this->assertNotNull($offer->current_version_id);
        $this->assertDatabaseHas('program_offer_versions', [
            'offer_id'        => $offer->id,
            'reward_model'    => 'percentage',
            'percentage_rate' => 10,
            'version_number'  => 1,
            'status'          => 'published',
        ]);
    }

    public function test_owner_can_create_offer_with_fixed_reward(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.offers.store', [self::TENANT_ID, $this->program->id]), [
                'name'         => 'Fixed Offer',
                'reward_model' => 'fixed',
                'fixed_amount' => 500,
            ])
            ->assertRedirect();

        $offer = ProgramOffer::where('program_id', $this->program->id)->where('name', 'Fixed Offer')->first();

        $this->assertNotNull($offer);
        $this->assertDatabaseHas('program_offer_versions', [
            'offer_id'     => $offer->id,
            'reward_model' => 'fixed',
            'fixed_amount' => 500,
        ]);
    }

    public function test_duplicate_code_within_same_program_rejected(): void
    {
        ProgramOffer::create([
            'tenant_id'  => self::TENANT_ID,
            'program_id' => $this->program->id,
            'name'       => 'Existing Offer',
            'code'       => 'DUPE',
            'status'     => 'active',
            'visibility' => 'public',
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.offers.store', [self::TENANT_ID, $this->program->id]), [
                'name'         => 'New Offer',
                'code'         => 'DUPE',
                'reward_model' => 'fixed',
                'fixed_amount' => 100,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_same_code_allowed_across_different_programs(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Second Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);

        ProgramOffer::create([
            'tenant_id'  => self::TENANT_ID,
            'program_id' => $otherProgram->id,
            'name'       => 'Existing Offer',
            'code'       => 'SHARED',
            'status'     => 'active',
            'visibility' => 'public',
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.offers.store', [self::TENANT_ID, $this->program->id]), [
                'name'         => 'New Offer',
                'code'         => 'SHARED',
                'reward_model' => 'fixed',
                'fixed_amount' => 100,
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
    }

    public function test_manager_without_manage_programs_cannot_create_offer(): void
    {
        $this->actingAs($this->managerUser, 'tenant')
            ->post(route('tenant.programs.offers.store', [self::TENANT_ID, $this->program->id]), [
                'name'         => 'Unauthorized Offer',
                'reward_model' => 'fixed',
                'fixed_amount' => 100,
            ])
            ->assertStatus(403);
    }

    public function test_owner_can_update_offer_status(): void
    {
        $offer = ProgramOffer::create([
            'tenant_id'  => self::TENANT_ID,
            'program_id' => $this->program->id,
            'name'       => 'Offer To Archive',
            'status'     => 'active',
            'visibility' => 'public',
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->patch(route('tenant.programs.offers.update', [self::TENANT_ID, $this->program->id, $offer->id]), [
                'status' => 'archived',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('program_offers', [
            'id'     => $offer->id,
            'status' => 'archived',
        ]);
    }

    public function test_offers_tab_paginates_at_20_per_page(): void
    {
        // created_at isn't in ProgramOffer::$fillable, so it's set explicitly
        // via a raw update after creation for deterministic, distinct
        // timestamps -- otherwise every row would share the same now() value
        // and ->latest() ordering among ties would be undefined.
        for ($i = 0; $i < 25; $i++) {
            $offer = ProgramOffer::create([
                'tenant_id'  => self::TENANT_ID,
                'program_id' => $this->program->id,
                'name'       => "Offer {$i}",
                'status'     => 'active',
                'visibility' => 'public',
            ]);
            \Illuminate\Support\Facades\DB::table('program_offers')
                ->where('id', $offer->id)
                ->update(['created_at' => now()->subMinutes(25 - $i)]); // Offer 24 newest
        }

        // Page 1 (default) shows the 20 newest: Offer 24 down to Offer 5.
        $page1 = $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=offers');
        $page1->assertStatus(200);
        $page1->assertSee('Offer 24');
        $page1->assertDontSee('Offer 0</td>', false);

        // Page 2 shows the remaining 5 oldest: Offer 4 down to Offer 0.
        // paginate()'s 3rd arg ('offers') is the literal query key, not
        // suffixed with "_page".
        $page2 = $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=offers&offers=2');
        $page2->assertStatus(200);
        $page2->assertSee('Offer 0');
    }

    public function test_offer_creation_failure_rolls_back_all_writes(): void
    {
        // One-shot DB::listen() hook simulating a mid-transaction failure
        // right after the offer row's INSERT completes, without touching
        // model internals or leaking state into other tests (the flag
        // disarms itself after firing once).
        $armed = true;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$armed) {
            if ($armed && str_contains($query->sql, 'insert into "program_offers"')) {
                $armed = false;
                throw new \RuntimeException('Simulated mid-transaction failure');
            }
        });

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.offers.store', [self::TENANT_ID, $this->program->id]), [
                'name'         => 'Should Roll Back',
                'reward_model' => 'fixed',
                'fixed_amount' => 100,
            ])
            ->assertStatus(500);

        // The offer INSERT executed (then we threw) -- if the transaction is
        // truly atomic, it must not be visible after rollback.
        $this->assertDatabaseMissing('program_offers', ['name' => 'Should Roll Back']);
        $this->assertDatabaseMissing('program_offer_versions', ['reward_model' => 'fixed']);
    }

    public function test_offer_from_other_program_returns_404(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Unrelated Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);
        $offer = ProgramOffer::create([
            'tenant_id'  => self::TENANT_ID,
            'program_id' => $otherProgram->id,
            'name'       => 'Other Program Offer',
            'status'     => 'active',
            'visibility' => 'public',
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->patch(route('tenant.programs.offers.update', [self::TENANT_ID, $this->program->id, $offer->id]), [
                'status' => 'archived',
            ])
            ->assertStatus(404);
    }

    // ── Schema / Fixtures ─────────────────────────────────────────────────────

    private function buildSchema(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('tenant_users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status')->default('active');
            $table->string('remember_token')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('tenant_user_id');
            $table->string('role')->default('viewer');
            $table->string('status')->default('active');
            $table->text('permissions_json')->nullable();
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('program_type')->default('referral');
            $table->string('status')->default('draft');
            $table->string('default_currency')->default('PHP');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('program_groups', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('program_offers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('program_group_id')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status')->default('active');
            $table->string('visibility')->default('public');
            $table->string('current_version_id')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('program_offer_versions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('offer_id');
            $table->unsignedInteger('version_number')->default(1);
            $table->string('status')->default('published');
            $table->string('currency')->nullable();
            $table->string('reward_model')->nullable();
            $table->string('qualifying_event')->nullable();
            $table->decimal('fixed_amount', 12, 4)->nullable();
            $table->decimal('percentage_rate', 12, 4)->nullable();
            $table->decimal('cap_amount', 12, 4)->nullable();
            $table->text('reward_rules')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->string('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('immutable_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_brand_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('logo_url')->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->string('sidebar_color', 7)->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    private function dropSchema(): void
    {
        Schema::dropIfExists('tenant_brand_profiles');
        Schema::dropIfExists('program_offer_versions');
        Schema::dropIfExists('program_offers');
        Schema::dropIfExists('program_groups');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }

    private function seedFixtures(): void
    {
        $this->tenant = Tenant::create([
            'id'     => self::TENANT_ID,
            'name'   => 'Test Offers Tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@offers-test.com',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        TenantMembership::create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => self::TENANT_ID,
            'tenant_user_id' => $this->ownerUser->id,
            'role'           => 'owner',
            'status'         => 'active',
        ]);

        $this->managerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'manager@offers-test.com',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        TenantMembership::create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => self::TENANT_ID,
            'tenant_user_id' => $this->managerUser->id,
            'role'           => 'manager',
            'status'         => 'active',
        ]);

        $this->program = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Test Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);
    }
}
