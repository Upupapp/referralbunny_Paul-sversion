<?php

namespace Tests\Feature;

use App\Models\MemberActionItem;
use App\Models\PartnerProgramMembership;
use App\Models\Program;
use App\Models\ProgramContract;
use App\Models\ReferrerProgramMembership;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the Programs V4 "Contracts" tab (propose + status
 * transitions, including the linked MemberActionItem lifecycle).
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramContractTest extends TestCase
{
    private const TENANT_ID    = 'test-contracts-tenant';
    private const OTHER_TENANT = 'test-contracts-other';

    private Tenant     $tenant;
    private TenantUser $ownerUser;
    private TenantUser $managerUser;
    private Program    $program;
    private string     $resellerId;
    private string     $partnerId;
    private string     $referrerMembershipId;
    private string     $partnerMembershipId;

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

    // ── propose ───────────────────────────────────────────────────────────────

    public function test_owner_can_propose_contract_for_active_referrer_membership(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.propose', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
            ])
            ->assertRedirect();

        $contract = ProgramContract::where('program_id', $this->program->id)
            ->where('membership_id', $this->referrerMembershipId)->first();

        $this->assertNotNull($contract);
        $this->assertSame('proposed', $contract->status);

        $this->assertDatabaseHas('member_action_items', [
            'membership_id' => $this->referrerMembershipId,
            'action_type'   => 'review_contract',
            'target_type'   => 'contract',
            'target_id'     => $contract->id,
            'status'        => 'pending',
        ]);
    }

    public function test_owner_can_propose_contract_for_active_partner_membership(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.propose', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'partner',
                'membership_id'   => $this->partnerMembershipId,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('program_contracts', [
            'program_id'    => $this->program->id,
            'membership_id' => $this->partnerMembershipId,
            'status'        => 'proposed',
        ]);
    }

    public function test_proposing_contract_for_membership_not_active_or_approved_is_rejected(): void
    {
        ReferrerProgramMembership::find($this->referrerMembershipId)->update(['status' => 'invited']);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.propose', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
            ])
            ->assertStatus(422);
    }

    public function test_proposing_second_contract_while_one_already_proposed_is_rejected(): void
    {
        $this->makeContract('proposed');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.propose', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
            ])
            ->assertStatus(422);
    }

    public function test_proposing_renewal_while_one_already_active_is_allowed_and_linked(): void
    {
        // Renewal path: proposing a new contract while one is active is
        // allowed (only a duplicate *proposal* is blocked) -- the new
        // contract links back via previous_contract_id, and accepting it
        // later supersedes the old one via the existing supersede logic.
        $activeContract = $this->makeContract('active');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.propose', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
            ])
            ->assertRedirect();

        $renewal = ProgramContract::where('membership_id', $this->referrerMembershipId)
            ->where('status', 'proposed')->first();

        $this->assertNotNull($renewal);
        $this->assertSame($activeContract->id, $renewal->previous_contract_id);

        // Accepting the renewal supersedes the old active contract.
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.status', [self::TENANT_ID, $this->program->id, $renewal->id]), [
                'status' => 'active',
            ])
            ->assertRedirect();

        $activeContract->refresh();
        $this->assertSame('superseded', $activeContract->status);
        $renewal->refresh();
        $this->assertSame('active', $renewal->status);
    }

    public function test_proposing_contract_after_previous_was_declined_is_allowed(): void
    {
        $this->makeContract('declined');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.propose', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
            ])
            ->assertRedirect();

        $this->assertSame(2, ProgramContract::where('membership_id', $this->referrerMembershipId)->count());
    }

    public function test_manager_without_manage_programs_cannot_propose_contract(): void
    {
        $this->actingAs($this->managerUser, 'tenant')
            ->post(route('tenant.programs.contracts.propose', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
            ])
            ->assertStatus(403);
    }

    public function test_propose_contract_failure_rolls_back_all_writes(): void
    {
        // One-shot DB::listen() hook simulating a mid-transaction failure
        // right after the contract row's INSERT completes -- proves the
        // linked MemberActionItem creation is atomic with the contract
        // creation, without touching model internals or leaking state into
        // other tests (the flag disarms itself after firing once).
        $armed = true;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$armed) {
            if ($armed && str_contains($query->sql, 'insert into "program_contracts"')) {
                $armed = false;
                throw new \RuntimeException('Simulated mid-transaction failure');
            }
        });

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.propose', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
            ])
            ->assertStatus(500);

        $this->assertDatabaseMissing('program_contracts', [
            'membership_id' => $this->referrerMembershipId,
        ]);
        $this->assertDatabaseMissing('member_action_items', [
            'membership_id' => $this->referrerMembershipId,
            'action_type'   => 'review_contract',
        ]);
    }

    public function test_propose_contract_for_membership_from_other_program_returns_404(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Other Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.propose', [self::TENANT_ID, $otherProgram->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
            ])
            ->assertStatus(404);
    }

    public function test_proposing_contract_with_offer_version_from_another_program_is_rejected(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Other Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);
        $offer = \App\Models\ProgramOffer::create([
            'tenant_id'  => self::TENANT_ID,
            'program_id' => $otherProgram->id,
            'name'       => 'Other Program Offer',
            'status'     => 'active',
            'visibility' => 'public',
        ]);
        $version = \App\Models\ProgramOfferVersion::create([
            'tenant_id'      => self::TENANT_ID,
            'program_id'     => $otherProgram->id,
            'offer_id'       => $offer->id,
            'version_number' => 1,
            'status'         => 'published',
            'reward_model'   => 'fixed',
            'fixed_amount'   => 100,
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.propose', [self::TENANT_ID, $this->program->id]), [
                'membership_type'  => 'referrer',
                'membership_id'    => $this->referrerMembershipId,
                'offer_version_id' => $version->id,
            ])
            ->assertStatus(404);

        $this->assertDatabaseMissing('program_contracts', [
            'membership_id' => $this->referrerMembershipId,
        ]);
    }

    // ── transition ────────────────────────────────────────────────────────────

    public function test_owner_can_transition_proposed_contract_to_active(): void
    {
        $contract = $this->makeContract('proposed');
        MemberActionItem::create([
            'tenant_id'       => self::TENANT_ID,
            'program_id'      => $this->program->id,
            'membership_type' => 'referrer',
            'membership_id'   => $this->referrerMembershipId,
            'action_type'     => 'review_contract',
            'target_type'     => 'contract',
            'target_id'       => $contract->id,
            'status'          => 'pending',
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.status', [self::TENANT_ID, $this->program->id, $contract->id]), [
                'status' => 'active',
            ])
            ->assertRedirect();

        $contract->refresh();
        $this->assertSame('active', $contract->status);
        $this->assertNotNull($contract->accepted_at);
        $this->assertSame('api', $contract->acceptance_method);

        $membership = ReferrerProgramMembership::find($this->referrerMembershipId);
        $this->assertSame($contract->id, $membership->active_contract_id);

        $this->assertDatabaseHas('member_action_items', [
            'target_id' => $contract->id,
            'status'    => 'completed',
        ]);
    }

    public function test_transitioning_to_active_supersedes_previously_active_contract_for_same_membership(): void
    {
        $firstActive = $this->makeContract('active');

        $second = ProgramContract::create([
            'tenant_id'       => self::TENANT_ID,
            'program_id'      => $this->program->id,
            'membership_type' => 'referrer',
            'membership_id'   => $this->referrerMembershipId,
            'status'          => 'proposed',
        ]);

        // Bypass the dedup guard by transitioning directly (simulating a contract
        // proposed before the first was superseded, e.g. a renewal workflow).
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.status', [self::TENANT_ID, $this->program->id, $second->id]), [
                'status' => 'active',
            ])
            ->assertRedirect();

        $firstActive->refresh();
        $this->assertSame('superseded', $firstActive->status);

        $second->refresh();
        $this->assertSame('active', $second->status);
    }

    public function test_manually_transitioning_to_superseded_clears_membership_active_contract(): void
    {
        $contract = $this->makeContract('active');
        ReferrerProgramMembership::find($this->referrerMembershipId)
            ->update(['active_contract_id' => $contract->id]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.status', [self::TENANT_ID, $this->program->id, $contract->id]), [
                'status' => 'superseded',
            ])
            ->assertRedirect();

        $contract->refresh();
        $this->assertSame('superseded', $contract->status);

        $membership = ReferrerProgramMembership::find($this->referrerMembershipId);
        $this->assertNull($membership->active_contract_id);
    }

    public function test_owner_can_decline_proposed_contract(): void
    {
        $contract = $this->makeContract('proposed');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.status', [self::TENANT_ID, $this->program->id, $contract->id]), [
                'status' => 'declined',
            ])
            ->assertRedirect();

        $contract->refresh();
        $this->assertSame('declined', $contract->status);
        $this->assertNotNull($contract->declined_at);
    }

    public function test_owner_can_end_active_contract_and_clears_membership_active_contract(): void
    {
        $contract = $this->makeContract('active');
        ReferrerProgramMembership::find($this->referrerMembershipId)
            ->update(['active_contract_id' => $contract->id]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.status', [self::TENANT_ID, $this->program->id, $contract->id]), [
                'status' => 'ended',
            ])
            ->assertRedirect();

        $contract->refresh();
        $this->assertSame('ended', $contract->status);
        $this->assertNotNull($contract->ended_at);

        $membership = ReferrerProgramMembership::find($this->referrerMembershipId);
        $this->assertNull($membership->active_contract_id);
    }

    public function test_illegal_contract_transition_rejected(): void
    {
        $contract = $this->makeContract('declined');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.status', [self::TENANT_ID, $this->program->id, $contract->id]), [
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $contract->refresh();
        $this->assertSame('declined', $contract->status);
    }

    public function test_manager_without_manage_programs_cannot_transition_contract(): void
    {
        $contract = $this->makeContract('proposed');

        $this->actingAs($this->managerUser, 'tenant')
            ->post(route('tenant.programs.contracts.status', [self::TENANT_ID, $this->program->id, $contract->id]), [
                'status' => 'active',
            ])
            ->assertStatus(403);
    }

    public function test_contract_scoped_to_wrong_program_returns_404(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Other Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);
        $contract = $this->makeContract('proposed');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.status', [self::TENANT_ID, $otherProgram->id, $contract->id]), [
                'status' => 'active',
            ])
            ->assertStatus(404);
    }

    // ── workspace rendering ──────────────────────────────────────────────────

    public function test_get_workspace_contracts_tab_renders(): void
    {
        $this->makeContract('proposed');

        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=contracts')
            ->assertStatus(200)
            ->assertSee('Propose contract');
    }

    public function test_get_workspace_other_tabs_render_without_error_when_contracts_exist(): void
    {
        $this->makeContract('proposed');

        // Phase 1 lesson: Blade renders every tab panel regardless of which is
        // active (x-show is CSS-only) — confirm Members/Offers/Settings panels
        // don't throw now that Contracts data is null on those requests.
        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=members')
            ->assertStatus(200);
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

        // QUEUE_CONNECTION=sync in tests means the ProgramActionItemCreated/
        // ProgramContractStatusChanged ShouldQueue listeners run inline during
        // propose()/transition() — needs this table. 'id' nullable:
        // Notification::$fillable doesn't include 'id', see
        // ProgramActionItemNotificationTest for the full explanation.
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
    }

    private function dropSchema(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('tenant_brand_profiles');
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
            'name'   => 'Test Contracts Tenant',
            'status' => 'active',
        ]);

        Tenant::create([
            'id'     => self::OTHER_TENANT,
            'name'   => 'Other Tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@contracts-test.com',
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
            'email'    => 'manager@contracts-test.com',
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

        $reseller = \App\Models\Reseller::create([
            'tenant_id' => self::TENANT_ID,
            'name'      => 'Test Referrer',
            'email'     => 'referrer@contracts-test.com',
            'status'    => 'active',
        ]);
        $this->resellerId = $reseller->id;

        $this->partnerId = (string) Str::uuid();
        \App\Models\Partner::create([
            'id'         => $this->partnerId,
            'tenant_id'  => self::TENANT_ID,
            'first_name' => 'Test',
            'last_name'  => 'Partner',
            'email'      => 'partner@contracts-test.com',
            'status'     => 'active',
        ]);

        $referrerMembership = ReferrerProgramMembership::create([
            'tenant_id'   => self::TENANT_ID,
            'program_id'  => $this->program->id,
            'reseller_id' => $this->resellerId,
            'status'      => 'active',
            'source'      => 'direct',
        ]);
        $this->referrerMembershipId = $referrerMembership->id;

        $partnerMembership = PartnerProgramMembership::create([
            'tenant_id'  => self::TENANT_ID,
            'program_id' => $this->program->id,
            'partner_id' => $this->partnerId,
            'status'     => 'active',
            'source'     => 'direct',
        ]);
        $this->partnerMembershipId = $partnerMembership->id;
    }
}
