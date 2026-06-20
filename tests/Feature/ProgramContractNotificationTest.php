<?php

namespace Tests\Feature;

use App\Events\ProgramActionItemCreated;
use App\Events\ProgramContractStatusChanged;
use App\Listeners\HandleProgramContractStatusChanged;
use App\Models\Program;
use App\Models\ProgramContract;
use App\Models\ReferrerProgramMembership;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the Programs V4 notification wiring on contract
 * proposal/transition: propose() fires ProgramActionItemCreated (for the
 * auto-created linked action item), and transition() fires
 * ProgramContractStatusChanged for active/declined transitions, handled by
 * the queued HandleProgramContractStatusChanged listener.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramContractNotificationTest extends TestCase
{
    private const TENANT_ID = 'test-contract-notif-tenant';

    private Tenant     $tenant;
    private TenantUser $ownerUser;
    private Program    $program;
    private string     $resellerId;
    private string     $referrerMembershipId;

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

    public function test_propose_fires_program_action_item_created_event(): void
    {
        Event::fake([ProgramActionItemCreated::class]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.propose', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
            ])
            ->assertRedirect();

        Event::assertDispatched(ProgramActionItemCreated::class);
    }

    public function test_transition_to_active_fires_status_changed_event(): void
    {
        Event::fake([ProgramContractStatusChanged::class]);
        $contract = $this->makeContract('proposed');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.status', [self::TENANT_ID, $this->program->id, $contract->id]), [
                'status' => 'active',
            ])
            ->assertRedirect();

        Event::assertDispatched(ProgramContractStatusChanged::class, fn ($e) => $e->contractId === $contract->id && $e->newStatus === 'active');
    }

    public function test_transition_to_superseded_does_not_fire_status_changed_event(): void
    {
        // Per design, only active/declined represent a decision worth notifying about.
        Event::fake([ProgramContractStatusChanged::class]);
        $contract = $this->makeContract('active');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.status', [self::TENANT_ID, $this->program->id, $contract->id]), [
                'status' => 'superseded',
            ])
            ->assertRedirect();

        Event::assertNotDispatched(ProgramContractStatusChanged::class);
    }

    public function test_listener_creates_notification_for_active_admin(): void
    {
        $contract = $this->makeContract('active');

        (new HandleProgramContractStatusChanged())->handle(new ProgramContractStatusChanged($contract->id, 'active'));

        $this->assertDatabaseHas('notifications', [
            'tenant_id'       => self::TENANT_ID,
            'notifiable_type' => 'tenant_admin',
            'notifiable_id'   => $this->ownerUser->id,
            'category'        => 'program_contract',
        ]);
    }

    public function test_listener_does_nothing_if_contract_status_no_longer_matches(): void
    {
        // Stale queued job: contract moved on to a different status before this ran.
        $contract = $this->makeContract('declined');

        (new HandleProgramContractStatusChanged())->handle(new ProgramContractStatusChanged($contract->id, 'active'));

        $this->assertDatabaseMissing('notifications', ['category' => 'program_contract']);
    }

    // ── Schema / Fixtures ─────────────────────────────────────────────────────

    private function makeContract(string $status): ProgramContract
    {
        return ProgramContract::create([
            'tenant_id'       => self::TENANT_ID,
            'program_id'      => $this->program->id,
            'membership_type' => 'referrer',
            'membership_id'   => $this->referrerMembershipId,
            'status'          => $status,
            'proposed_at'     => now(),
        ]);
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
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('nickname')->nullable();
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

        Schema::create('program_contracts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('membership_type');
            $table->string('membership_id');
            $table->string('offer_version_id')->nullable();
            $table->string('program_terms_version_id')->nullable();
            $table->string('group_terms_version_id')->nullable();
            $table->string('individual_override_version_id')->nullable();
            $table->string('status')->default('proposed');
            $table->timestamp('proposed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('acceptance_ip', 45)->nullable();
            $table->text('acceptance_user_agent')->nullable();
            $table->string('acceptance_method')->nullable();
            $table->text('acceptance_evidence')->nullable();
            $table->string('previous_contract_id')->nullable();
            $table->timestamps();
        });

        Schema::create('member_action_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('membership_type');
            $table->string('membership_id');
            $table->string('action_type');
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('source_configuration_version_id')->nullable();
            $table->string('notification_status')->default('not_sent');
            $table->timestamps();
        });

        // See ProgramActionItemNotificationTest for why 'id' is nullable here:
        // Notification::$fillable doesn't include 'id', and production
        // apparently relies on a DB-side default that sqlite lacks.
        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id')->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('notifiable_type')->nullable();
            $table->string('notifiable_id')->nullable();
            $table->string('category')->nullable();
            $table->string('type')->nullable();
            $table->string('priority')->default('normal');
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('action_url')->nullable();
            $table->string('action_label')->nullable();
            $table->string('channel')->nullable();
            $table->string('frequency_type')->nullable();
            $table->integer('escalation_level')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_dismissed')->default(false);
            $table->string('deduplication_key')->nullable();
            $table->text('metadata_json')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();
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
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('member_action_items');
        Schema::dropIfExists('program_contracts');
        Schema::dropIfExists('program_offer_versions');
        Schema::dropIfExists('program_offers');
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
            'name'   => 'Test Contract Notif Tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'         => (string) Str::uuid(),
            'email'      => 'owner@contract-notif-test.com',
            'password'   => bcrypt('password'),
            'status'     => 'active',
            'first_name' => 'Test',
            'last_name'  => 'Owner',
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

        $reseller = \App\Models\Reseller::create([
            'tenant_id' => self::TENANT_ID,
            'name'      => 'Test Referrer',
            'email'     => 'referrer@contract-notif-test.com',
            'status'    => 'active',
        ]);
        $this->resellerId = $reseller->id;

        $referrerMembership = ReferrerProgramMembership::create([
            'tenant_id'   => self::TENANT_ID,
            'program_id'  => $this->program->id,
            'reseller_id' => $this->resellerId,
            'status'      => 'active',
            'source'      => 'direct',
        ]);
        $this->referrerMembershipId = $referrerMembership->id;
    }
}
