<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the Programs V4 "Settings" tab, which reuses
 * ProgramWorkspaceController::update() with an extended field set.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramSettingsTabTest extends TestCase
{
    private const TENANT_ID = 'test-settings-tenant';

    private Tenant     $tenant;
    private TenantUser $ownerUser;
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

    public function test_attribution_fields_persist(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->patch(route('tenant.programs.update', [self::TENANT_ID, $this->program->id]), [
                'attribution_model'       => 'last_touch',
                'attribution_window_days' => 45,
                'referral_expiry_days'    => 90,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('programs', [
            'id'                       => $this->program->id,
            'attribution_model'        => 'last_touch',
            'attribution_window_days'  => 45,
            'referral_expiry_days'     => 90,
        ]);
    }

    public function test_policy_fields_persist_with_corrected_uniqueness_enum(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->patch(route('tenant.programs.update', [self::TENANT_ID, $this->program->id]), [
                'duplicate_referral_policy'       => 'flag',
                'organization_uniqueness_policy'  => 'allow_multiple',
                'existing_customer_policy'        => 'reject',
                'self_referral_policy'            => 'allow',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('programs', [
            'id'                              => $this->program->id,
            'duplicate_referral_policy'       => 'flag',
            'organization_uniqueness_policy'  => 'allow_multiple',
            'existing_customer_policy'        => 'reject',
            'self_referral_policy'            => 'allow',
        ]);
    }

    public function test_invalid_uniqueness_policy_value_rejected(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->patch(route('tenant.programs.update', [self::TENANT_ID, $this->program->id]), [
                'organization_uniqueness_policy' => 'reject',
            ])
            ->assertSessionHasErrors('organization_uniqueness_policy');
    }

    public function test_enrollment_and_referral_period_dates_persist(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->patch(route('tenant.programs.update', [self::TENANT_ID, $this->program->id]), [
                'enrollment_opens_at'        => '2026-07-01 00:00:00',
                'enrollment_closes_at'       => '2026-07-31 23:59:59',
                'referral_period_opens_at'   => '2026-07-01 00:00:00',
                'referral_period_closes_at'  => '2026-12-31 23:59:59',
            ])
            ->assertRedirect();

        $this->program->refresh();
        $this->assertSame('2026-07-01 00:00:00', $this->program->enrollment_opens_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-31 23:59:59', $this->program->enrollment_closes_at->format('Y-m-d H:i:s'));
    }

    public function test_invalid_date_ordering_rejected(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->patch(route('tenant.programs.update', [self::TENANT_ID, $this->program->id]), [
                'enrollment_opens_at'  => '2026-07-31 00:00:00',
                'enrollment_closes_at' => '2026-07-01 00:00:00',
            ])
            ->assertSessionHasErrors('enrollment_closes_at');
    }

    public function test_get_workspace_settings_tab_renders_current_values(): void
    {
        $this->program->update(['attribution_model' => 'manual']);

        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=settings')
            ->assertStatus(200)
            ->assertSee('Attribution model');
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
            $table->string('public_visibility')->default('private');
            $table->string('application_mode')->default('invite_only');
            $table->string('approval_mode')->default('manual');
            $table->string('attribution_model')->default('last_touch');
            $table->unsignedInteger('attribution_window_days')->default(30);
            $table->unsignedInteger('referral_expiry_days')->nullable();
            $table->string('duplicate_referral_policy')->default('reject');
            $table->string('organization_uniqueness_policy')->default('one_per_org');
            $table->string('existing_customer_policy')->default('reject');
            $table->string('self_referral_policy')->default('reject');
            $table->string('timezone')->default('UTC');
            $table->string('default_currency')->default('PHP');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('enrollment_opens_at')->nullable();
            $table->timestamp('enrollment_closes_at')->nullable();
            $table->timestamp('referral_period_opens_at')->nullable();
            $table->timestamp('referral_period_closes_at')->nullable();
            $table->boolean('evergreen')->default(true);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
        Schema::dropIfExists('programs');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }

    private function seedFixtures(): void
    {
        $this->tenant = Tenant::create([
            'id'     => self::TENANT_ID,
            'name'   => 'Test Settings Tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@settings-test.com',
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

        $this->program = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Test Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);
    }
}
