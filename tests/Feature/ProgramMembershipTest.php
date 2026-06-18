<?php

namespace Tests\Feature;

use App\Models\PartnerProgramMembership;
use App\Models\Program;
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
 * Feature tests for the Programs V4 "Members" tab (attach + status
 * transitions for referrer/partner memberships).
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramMembershipTest extends TestCase
{
    private const TENANT_ID    = 'test-members-tenant';
    private const OTHER_TENANT = 'test-members-other';

    private Tenant     $tenant;
    private TenantUser $ownerUser;
    private TenantUser $managerUser;
    private Program    $program;
    private string     $resellerId;
    private string     $partnerId;

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

    // ── attach referrer ──────────────────────────────────────────────────────

    public function test_owner_can_attach_referrer(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.members.referrers.attach', [self::TENANT_ID, $this->program->id]), [
                'reseller_id' => $this->resellerId,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('referrer_program_memberships', [
            'program_id'  => $this->program->id,
            'reseller_id' => $this->resellerId,
            'status'      => 'active',
            'source'      => 'direct',
        ]);
    }

    public function test_manager_without_manage_programs_cannot_attach_referrer(): void
    {
        $this->actingAs($this->managerUser, 'tenant')
            ->post(route('tenant.programs.members.referrers.attach', [self::TENANT_ID, $this->program->id]), [
                'reseller_id' => $this->resellerId,
            ])
            ->assertStatus(403);
    }

    public function test_duplicate_active_referrer_membership_rejected(): void
    {
        ReferrerProgramMembership::create([
            'tenant_id'   => self::TENANT_ID,
            'program_id'  => $this->program->id,
            'reseller_id' => $this->resellerId,
            'status'      => 'active',
            'source'      => 'direct',
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.members.referrers.attach', [self::TENANT_ID, $this->program->id]), [
                'reseller_id' => $this->resellerId,
            ])
            ->assertStatus(422);
    }

    public function test_re_attaching_a_removed_referrer_revives_the_existing_row(): void
    {
        // (program_id, reseller_id) has a DB-level unique constraint, so a
        // removed membership must be revived in place rather than re-inserted.
        $membership = $this->makeReferrerMembership('removed');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.members.referrers.attach', [self::TENANT_ID, $this->program->id]), [
                'reseller_id' => $this->resellerId,
            ])
            ->assertRedirect();

        $this->assertSame(1, ReferrerProgramMembership::forProgram($this->program->id)
            ->forReseller($this->resellerId)->count());
        $membership->refresh();
        $this->assertSame('active', $membership->status);
    }

    public function test_program_from_other_tenant_returns_404_on_attach(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::OTHER_TENANT,
            'name'         => 'Other Tenant Program',
            'program_type' => 'referral',
            'status'       => 'draft',
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.members.referrers.attach', [self::TENANT_ID, $otherProgram->id]), [
                'reseller_id' => $this->resellerId,
            ])
            ->assertStatus(404);
    }

    // ── attach partner ───────────────────────────────────────────────────────

    public function test_owner_can_attach_partner(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.members.partners.attach', [self::TENANT_ID, $this->program->id]), [
                'partner_id' => $this->partnerId,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('partner_program_memberships', [
            'program_id' => $this->program->id,
            'partner_id' => $this->partnerId,
            'status'     => 'active',
        ]);
    }

    // ── status transitions: referrer ─────────────────────────────────────────

    public function test_owner_can_approve_invited_referrer(): void
    {
        $membership = $this->makeReferrerMembership('invited');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.members.referrers.status', [self::TENANT_ID, $this->program->id, $membership->id]), [
                'status' => 'approved',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('referrer_program_memberships', [
            'id'     => $membership->id,
            'status' => 'approved',
        ]);
    }

    public function test_owner_can_activate_approved_referrer(): void
    {
        $membership = $this->makeReferrerMembership('approved');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.members.referrers.status', [self::TENANT_ID, $this->program->id, $membership->id]), [
                'status' => 'active',
            ]);

        $membership->refresh();
        $this->assertSame('active', $membership->status);
        $this->assertNotNull($membership->activated_at);
    }

    public function test_owner_can_pause_active_referrer(): void
    {
        $membership = $this->makeReferrerMembership('active');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.members.referrers.status', [self::TENANT_ID, $this->program->id, $membership->id]), [
                'status' => 'paused',
            ]);

        $this->assertDatabaseHas('referrer_program_memberships', [
            'id'     => $membership->id,
            'status' => 'paused',
        ]);
    }

    public function test_owner_can_suspend_and_remove_referrer(): void
    {
        $membership = $this->makeReferrerMembership('suspended');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.members.referrers.status', [self::TENANT_ID, $this->program->id, $membership->id]), [
                'status' => 'removed',
            ]);

        $this->assertDatabaseHas('referrer_program_memberships', [
            'id'     => $membership->id,
            'status' => 'removed',
        ]);
    }

    public function test_illegal_referrer_transition_rejected_and_status_unchanged(): void
    {
        // removed is terminal — no transitions allowed out of it.
        $membership = $this->makeReferrerMembership('removed');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.members.referrers.status', [self::TENANT_ID, $this->program->id, $membership->id]), [
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('referrer_program_memberships', [
            'id'     => $membership->id,
            'status' => 'removed',
        ]);
    }

    public function test_manager_without_manage_programs_cannot_transition_referrer(): void
    {
        $membership = $this->makeReferrerMembership('invited');

        $this->actingAs($this->managerUser, 'tenant')
            ->post(route('tenant.programs.members.referrers.status', [self::TENANT_ID, $this->program->id, $membership->id]), [
                'status' => 'approved',
            ])
            ->assertStatus(403);
    }

    public function test_referrer_membership_scoped_to_wrong_program_returns_404(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Another Program',
            'program_type' => 'referral',
            'status'       => 'draft',
        ]);
        $membership = $this->makeReferrerMembership('invited');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.members.referrers.status', [self::TENANT_ID, $otherProgram->id, $membership->id]), [
                'status' => 'approved',
            ])
            ->assertStatus(404);
    }

    // ── status transitions: partner (mirror check) ──────────────────────────

    public function test_owner_can_approve_invited_partner(): void
    {
        $membership = PartnerProgramMembership::create([
            'tenant_id'  => self::TENANT_ID,
            'program_id' => $this->program->id,
            'partner_id' => $this->partnerId,
            'status'     => 'invited',
            'source'     => 'direct',
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.members.partners.status', [self::TENANT_ID, $this->program->id, $membership->id]), [
                'status' => 'approved',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('partner_program_memberships', [
            'id'     => $membership->id,
            'status' => 'approved',
        ]);
    }

    // ── Schema / Fixtures ─────────────────────────────────────────────────────

    private function makeReferrerMembership(string $status): ReferrerProgramMembership
    {
        return ReferrerProgramMembership::create([
            'tenant_id'   => self::TENANT_ID,
            'program_id'  => $this->program->id,
            'reseller_id' => $this->resellerId,
            'status'      => $status,
            'source'      => 'direct',
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

        Schema::create('program_groups', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('name');
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
            $table->string('invitation_id')->nullable();
            $table->string('application_id')->nullable();
            $table->string('active_contract_id')->nullable();
            $table->string('assigned_manager_id')->nullable();
            $table->string('referral_code')->nullable();
            $table->text('tags')->nullable();
            $table->text('metadata')->nullable();
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
            $table->string('invitation_id')->nullable();
            $table->string('active_contract_id')->nullable();
            $table->string('assigned_manager_id')->nullable();
            $table->text('tags')->nullable();
            $table->text('metadata')->nullable();
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
    }

    private function dropSchema(): void
    {
        Schema::dropIfExists('partner_program_memberships');
        Schema::dropIfExists('referrer_program_memberships');
        Schema::dropIfExists('program_groups');
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
            'name'   => 'Test Members Tenant',
            'status' => 'active',
        ]);

        Tenant::create([
            'id'     => self::OTHER_TENANT,
            'name'   => 'Other Tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@members-test.com',
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
            'email'    => 'manager@members-test.com',
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

        // Reseller::$fillable does not include 'id' — the model's boot() hook
        // generates the UUID, so capture it after create() rather than pre-assigning.
        $reseller = \App\Models\Reseller::create([
            'tenant_id' => self::TENANT_ID,
            'name'      => 'Test Referrer',
            'email'     => 'referrer@members-test.com',
            'status'    => 'active',
        ]);
        $this->resellerId = $reseller->id;

        $this->partnerId = (string) Str::uuid();
        \App\Models\Partner::create([
            'id'         => $this->partnerId,
            'tenant_id'  => self::TENANT_ID,
            'first_name' => 'Test',
            'last_name'  => 'Partner',
            'email'      => 'partner@members-test.com',
            'status'     => 'active',
        ]);
    }
}
