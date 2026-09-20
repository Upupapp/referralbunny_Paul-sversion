<?php

namespace Tests\Feature;

use App\Models\PartnerProgramMembership;
use App\Models\Program;
use App\Models\ReferrerProgramMembership;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use App\Services\CommissionCalculationService;
use App\Services\ProgramAnalyticsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the Programs V4 "Analytics" tab — a read-only
 * program-wide deal/commission/member metrics dashboard.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramAnalyticsTest extends TestCase
{
    private const TENANT_ID = 'test-analytics-tenant';

    private Tenant     $tenant;
    private TenantUser $ownerUser;
    private Program    $program;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('programs.enabled', true);
        $this->buildSchema();
        foreach ([
            '2026_06_13_000001_create_referral_program_setup_tables.php',
            '2026_06_17_200006_create_program_offers_table.php',
            '2026_06_17_200007_create_program_offer_versions_table.php',
            '2026_09_20_000001_create_program_connections.php',
            '2026_09_20_000004_add_program_operating_mode.php',
        ] as $file) (require database_path('migrations/'.$file))->up();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    public function test_zero_state_renders_zeroed_metrics_for_program_with_no_leads(): void
    {
        $metrics = app(ProgramAnalyticsService::class)->compute($this->program);

        $this->assertSame(0, $metrics['total_deals']);
        $this->assertSame(0, $metrics['active_deals']);
        $this->assertSame(0.0, $metrics['total_deal_value']);
        $this->assertNull($metrics['conversion_rate']);
        $this->assertSame(0.0, $metrics['pending_commission']);
        $this->assertSame(0, $metrics['referrer_total']);
        $this->assertSame(0, $metrics['partner_total']);
    }

    public function test_deal_counts_and_value_computed_correctly(): void
    {
        $this->makeLead(['status' => 'active',   'stage' => 'introduction', 'deal_value' => 1000]);
        $this->makeLead(['status' => 'active',   'stage' => 'paid',         'deal_value' => 2000]);
        $this->makeLead(['status' => 'expiring', 'stage' => 'negotiation',  'deal_value' => 500]);
        $this->makeLead(['status' => 'archived', 'stage' => 'paid',         'deal_value' => 9999]); // excluded

        $metrics = app(ProgramAnalyticsService::class)->compute($this->program);

        $this->assertSame(3, $metrics['total_deals']); // archived excluded
        $this->assertSame(3, $metrics['active_deals']); // active + expiring
        $this->assertSame(1, $metrics['expiring_deals']);
        $this->assertSame(1, $metrics['closed_won_deals']); // stage=paid, status=active
        $this->assertSame(3500.0, $metrics['total_deal_value']);
        $this->assertSame(round(3500 / 3, 2), $metrics['average_deal_value']);
        $this->assertSame(round(1 / 3 * 100, 1), $metrics['conversion_rate']);
    }

    public function test_commission_totals_computed_per_status(): void
    {
        $pr = CommissionCalculationService::COMMISSION_POOL_RATE;

        $this->makeLead(['status' => 'active', 'added_amount' => 1000, 'commission_status' => 'pending']);
        $this->makeLead(['status' => 'active', 'added_amount' => 2000, 'commission_status' => 'locked']);
        $this->makeLead(['status' => 'active', 'added_amount' => 3000, 'commission_status' => 'paid']);

        $metrics = app(ProgramAnalyticsService::class)->compute($this->program);

        $this->assertSame(round(1000 * $pr, 2), round($metrics['pending_commission'], 2));
        $this->assertSame(round(2000 * $pr, 2), round($metrics['locked_commission'], 2));
        $this->assertSame(round(3000 * $pr, 2), round($metrics['paid_commission'], 2));
    }

    public function test_leads_from_other_program_are_excluded(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Other Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);
        $this->makeLead(['status' => 'active', 'deal_value' => 5000], $otherProgram->id);
        $this->makeLead(['status' => 'active', 'deal_value' => 100]);

        $metrics = app(ProgramAnalyticsService::class)->compute($this->program);

        $this->assertSame(1, $metrics['total_deals']);
        $this->assertSame(100.0, $metrics['total_deal_value']);
    }

    public function test_member_counts_reflect_active_vs_total(): void
    {
        $reseller = \App\Models\Reseller::create([
            'tenant_id' => self::TENANT_ID, 'name' => 'R1', 'email' => 'r1@test.com', 'status' => 'active',
        ]);
        ReferrerProgramMembership::create([
            'tenant_id' => self::TENANT_ID, 'program_id' => $this->program->id,
            'reseller_id' => $reseller->id, 'status' => 'active', 'source' => 'direct',
        ]);
        $reseller2 = \App\Models\Reseller::create([
            'tenant_id' => self::TENANT_ID, 'name' => 'R2', 'email' => 'r2@test.com', 'status' => 'active',
        ]);
        ReferrerProgramMembership::create([
            'tenant_id' => self::TENANT_ID, 'program_id' => $this->program->id,
            'reseller_id' => $reseller2->id, 'status' => 'removed', 'source' => 'direct',
        ]);

        $metrics = app(ProgramAnalyticsService::class)->compute($this->program);

        $this->assertSame(2, $metrics['referrer_total']);
        $this->assertSame(1, $metrics['referrer_active']);
    }

    public function test_manual_performance_uses_assigned_stages_without_fixed_commission(): void
    {
        $this->program->update(['operating_mode'=>'manual']);
        $this->makeLead(['stage'=>'demo_complete']);
        $this->makeLead(['stage'=>'custom_won', 'status'=>'expiring']);
        $this->makeLead(['stage'=>'custom_won', 'status'=>'archived']);
        $this->makeLead(['stage'=>'other'], 'other-program');
        $result = app(\App\Services\Programs\ProgramPerformanceSummary::class)->compute($this->program);
        $this->assertSame('manual', $result['mode']);
        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['expiring']);
        $this->assertSame(['custom_won', 'demo_complete'], $result['stages']->pluck('stage')->all());
        $this->assertArrayNotHasKey('pending_commission', $result);
    }

    // ── workspace rendering ──────────────────────────────────────────────────

    public function test_get_workspace_analytics_tab_renders(): void
    {
        $this->makeLead(['status' => 'active', 'deal_value' => 100]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=analytics')
            ->assertStatus(200)
            ->assertSee('Total referrals')
            ->assertDontSee('Pending</p>', false);
    }

    public function test_get_workspace_other_tabs_render_without_error_when_analytics_data_is_null(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=members')
            ->assertStatus(200);
    }

    // ── Schema / Fixtures ─────────────────────────────────────────────────────

    private function makeLead(array $overrides = [], ?string $programId = null): void
    {
        DB::table('leads')->insert(array_merge([
            'id'                => (string) Str::uuid(),
            'tenant_id'         => self::TENANT_ID,
            'program_id'        => $programId ?? $this->program->id,
            'name'              => 'Test Lead',
            'status'            => 'active',
            'stage'             => 'introduction',
            'deal_value'        => 0,
            'added_amount'      => 0,
            'commission_status' => 'pending',
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $overrides));
    }

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

        Schema::create('resellers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('email');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('partner_users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('referrer_program_memberships', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('reseller_id');
            $table->string('program_group_id')->nullable();
            $table->string('status')->default('invited');
            $table->string('source')->default('direct');
            $table->string('active_contract_id')->nullable();
            $table->string('assigned_manager_id')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->unique(['program_id', 'reseller_id']);
        });

        Schema::create('partner_program_memberships', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('partner_id');
            $table->string('program_group_id')->nullable();
            $table->string('status')->default('invited');
            $table->string('source')->default('direct');
            $table->string('active_contract_id')->nullable();
            $table->string('assigned_manager_id')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->unique(['program_id', 'partner_id']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id')->nullable();
            $table->string('name')->nullable();
            $table->string('status')->default('active');
            $table->string('stage')->nullable();
            $table->decimal('deal_value', 12, 2)->default(0);
            $table->decimal('added_amount', 12, 2)->default(0);
            $table->string('commission_status')->nullable();
            $table->string('reseller_name')->nullable();
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
        Schema::dropIfExists('program_offer_versions');
        Schema::dropIfExists('program_offers');
        Schema::dropIfExists('program_connections');
        Schema::dropIfExists('program_conversion_events');
        Schema::dropIfExists('tenant_brand_profiles');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('partner_program_memberships');
        Schema::dropIfExists('referrer_program_memberships');
        Schema::dropIfExists('partner_users');
        Schema::dropIfExists('resellers');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }

    private function seedFixtures(): void
    {
        $this->tenant = Tenant::create([
            'id'     => self::TENANT_ID,
            'name'   => 'Test Analytics Tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@analytics-test.com',
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
