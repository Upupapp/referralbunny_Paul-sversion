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
 * Feature tests for the Programs V4 "Action Items" tab (manual creation +
 * completion/dismissal), plus an integration test of the Contracts→Action
 * Items linkage.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramActionItemTest extends TestCase
{
    private const TENANT_ID = 'test-action-items-tenant';

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

    // ── create ────────────────────────────────────────────────────────────────

    public function test_owner_can_create_action_item_for_referrer_membership(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.action-items.store', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
                'action_type'     => 'complete_profile',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('member_action_items', [
            'membership_id' => $this->referrerMembershipId,
            'action_type'   => 'complete_profile',
            'status'        => 'pending',
            // Phase 5: ProgramActionItemCreated's listener runs synchronously
            // under the test queue driver and flips this to 'sent' immediately.
            'notification_status' => 'sent',
        ]);
    }

    public function test_owner_can_create_action_item_for_partner_membership(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.action-items.store', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'partner',
                'membership_id'   => $this->partnerMembershipId,
                'action_type'     => 'upload_document',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('member_action_items', [
            'membership_id' => $this->partnerMembershipId,
            'action_type'   => 'upload_document',
        ]);
    }

    public function test_owner_can_create_action_item_with_due_at(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.action-items.store', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
                'action_type'     => 'accept_terms',
                'due_at'          => '2026-08-01 00:00:00',
            ])
            ->assertRedirect();

        $item = MemberActionItem::where('membership_id', $this->referrerMembershipId)->first();
        $this->assertSame('2026-08-01 00:00:00', $item->due_at->format('Y-m-d H:i:s'));
    }

    public function test_manager_without_manage_programs_cannot_create_action_item(): void
    {
        $this->actingAs($this->managerUser, 'tenant')
            ->post(route('tenant.programs.action-items.store', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
                'action_type'     => 'complete_profile',
            ])
            ->assertStatus(403);
    }

    public function test_create_action_item_for_membership_from_other_program_returns_404(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Other Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.action-items.store', [self::TENANT_ID, $otherProgram->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
                'action_type'     => 'complete_profile',
            ])
            ->assertStatus(404);
    }

    // ── transition ────────────────────────────────────────────────────────────

    public function test_owner_can_complete_pending_action_item(): void
    {
        $item = $this->makeActionItem('pending');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.action-items.status', [self::TENANT_ID, $this->program->id, $item->id]), [
                'status' => 'completed',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('completed', $item->status);
        $this->assertNotNull($item->completed_at);
    }

    public function test_owner_can_dismiss_pending_action_item(): void
    {
        $item = $this->makeActionItem('pending');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.action-items.status', [self::TENANT_ID, $this->program->id, $item->id]), [
                'status' => 'dismissed',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('dismissed', $item->status);
        $this->assertNull($item->completed_at);
    }

    public function test_completing_an_already_completed_action_item_is_rejected(): void
    {
        $item = $this->makeActionItem('completed');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.action-items.status', [self::TENANT_ID, $this->program->id, $item->id]), [
                'status' => 'completed',
            ])
            ->assertStatus(422);
    }

    public function test_manager_without_manage_programs_cannot_transition_action_item(): void
    {
        $item = $this->makeActionItem('pending');

        $this->actingAs($this->managerUser, 'tenant')
            ->post(route('tenant.programs.action-items.status', [self::TENANT_ID, $this->program->id, $item->id]), [
                'status' => 'completed',
            ])
            ->assertStatus(403);
    }

    public function test_action_item_scoped_to_wrong_program_returns_404(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Other Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);
        $item = $this->makeActionItem('pending');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.action-items.status', [self::TENANT_ID, $otherProgram->id, $item->id]), [
                'status' => 'completed',
            ])
            ->assertStatus(404);
    }

    // ── Contracts → Action Items linkage (integration) ──────────────────────────

    public function test_action_item_auto_created_by_contract_proposal_links_back_via_target(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.propose', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
            ])
            ->assertRedirect();

        $contract = ProgramContract::where('membership_id', $this->referrerMembershipId)->first();
        $item     = MemberActionItem::where('target_type', 'contract')->where('target_id', $contract->id)->first();

        $this->assertNotNull($item);
        $this->assertSame('review_contract', $item->action_type);
        $this->assertSame('pending', $item->status);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.contracts.status', [self::TENANT_ID, $this->program->id, $contract->id]), [
                'status' => 'active',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('completed', $item->status);
        $this->assertNotNull($item->completed_at);
    }

    // ── workspace rendering ──────────────────────────────────────────────────

    public function test_get_workspace_action_items_tab_renders(): void
    {
        $this->makeActionItem('pending');

        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=action-items')
            ->assertStatus(200)
            ->assertSee('New action item');
    }

    // ── Schema / Fixtures ─────────────────────────────────────────────────────

    private function makeActionItem(string $status): MemberActionItem
    {
        return MemberActionItem::create([
            'tenant_id'           => self::TENANT_ID,
            'program_id'          => $this->program->id,
            'membership_type'     => 'referrer',
            'membership_id'       => $this->referrerMembershipId,
            'action_type'         => 'complete_profile',
            'status'              => $status,
            'notification_status' => 'not_sent',
            'completed_at'        => $status === 'completed' ? now() : null,
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

        // QUEUE_CONNECTION=sync in tests means ProgramActionItemCreated's
        // ShouldQueue listener runs inline during store() — needs this table.
        // 'id' nullable: Notification::$fillable doesn't include 'id', see
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
            'name'   => 'Test Action Items Tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@action-items-test.com',
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
            'email'    => 'manager@action-items-test.com',
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
            'email'     => 'referrer@action-items-test.com',
            'status'    => 'active',
        ]);
        $this->resellerId = $reseller->id;

        $this->partnerId = (string) Str::uuid();
        \App\Models\Partner::create([
            'id'         => $this->partnerId,
            'tenant_id'  => self::TENANT_ID,
            'first_name' => 'Test',
            'last_name'  => 'Partner',
            'email'      => 'partner@action-items-test.com',
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
