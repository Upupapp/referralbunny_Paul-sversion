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
 * Feature tests for Programs V4 admin (tenant portal) flows.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 * Feature flag (programs.enabled) is forced true for all tests.
 */
class ProgramAdminTest extends TestCase
{
    private const TENANT_ID      = 'test-programs-tenant';
    private const OTHER_TENANT   = 'test-programs-other';

    private Tenant     $tenant;
    private Tenant     $otherTenant;
    private TenantUser $ownerUser;
    private TenantUser $managerUser;
    private TenantUser $viewerUser;

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

    // ── Feature flag ──────────────────────────────────────────────────────────

    public function test_programs_index_returns_404_when_flag_disabled(): void
    {
        Config::set('programs.enabled', false);

        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.index', self::TENANT_ID))
            ->assertStatus(404);
    }

    // ── viewAny (index) ───────────────────────────────────────────────────────

    public function test_owner_can_view_programs_index(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.index', self::TENANT_ID))
            ->assertStatus(200);
    }

    public function test_manager_with_view_programs_can_view_index(): void
    {
        $this->actingAs($this->managerUser, 'tenant')
            ->get(route('tenant.programs.index', self::TENANT_ID))
            ->assertStatus(200);
    }

    public function test_viewer_cannot_view_programs_index(): void
    {
        $this->actingAs($this->viewerUser, 'tenant')
            ->get(route('tenant.programs.index', self::TENANT_ID))
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_redirected_from_index(): void
    {
        $this->get(route('tenant.programs.index', self::TENANT_ID))
            ->assertRedirect();
    }

    // ── create / store ────────────────────────────────────────────────────────

    public function test_owner_can_create_program(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.create', self::TENANT_ID))
            ->assertStatus(200);
    }

    public function test_store_creates_program_with_correct_tenant(): void
    {
        $response = $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.store', self::TENANT_ID), [
                'name'         => 'My Test Program',
                'program_type' => 'referral',
            ]);

        $response->assertRedirect();

        $program = Program::where('tenant_id', self::TENANT_ID)
            ->where('name', 'My Test Program')
            ->first();

        $this->assertNotNull($program);
        $this->assertSame('draft', $program->status);
        $this->assertSame(self::TENANT_ID, $program->tenant_id);
    }

    public function test_store_always_assigns_route_tenant_id(): void
    {
        // Even if a malicious request tries to set a different tenant_id,
        // the controller always uses the route parameter.
        $response = $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.store', self::TENANT_ID), [
                'name'         => 'Injected Tenant Program',
                'program_type' => 'referral',
                'tenant_id'    => self::OTHER_TENANT,  // attempt injection
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('programs', [
            'name'      => 'Injected Tenant Program',
            'tenant_id' => self::TENANT_ID,  // route tenant wins
        ]);
        $this->assertDatabaseMissing('programs', [
            'name'      => 'Injected Tenant Program',
            'tenant_id' => self::OTHER_TENANT,
        ]);
    }

    public function test_manager_without_manage_programs_cannot_store(): void
    {
        // The managerUser has view_programs=true but manage_programs=false by default
        $this->actingAs($this->managerUser, 'tenant')
            ->post(route('tenant.programs.store', self::TENANT_ID), [
                'name'         => 'Unauthorized Program',
                'program_type' => 'referral',
            ])
            ->assertStatus(403);
    }

    // ── workspace (show) ──────────────────────────────────────────────────────

    public function test_owner_can_view_workspace(): void
    {
        $program = $this->makeProgram();

        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $program->id]))
            ->assertStatus(200)
            ->assertSee($program->name);
    }

    public function test_program_from_other_tenant_returns_404(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::OTHER_TENANT,
            'name'         => 'Other Tenant Program',
            'program_type' => 'referral',
            'status'       => 'draft',
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $otherProgram->id]))
            ->assertStatus(404);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_owner_can_update_program(): void
    {
        $program = $this->makeProgram();

        $this->actingAs($this->ownerUser, 'tenant')
            ->patch(route('tenant.programs.update', [self::TENANT_ID, $program->id]), [
                'name' => 'Updated Name',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('programs', [
            'id'   => $program->id,
            'name' => 'Updated Name',
        ]);
    }

    // ── lifecycle ─────────────────────────────────────────────────────────────

    public function test_owner_can_launch_draft_program(): void
    {
        $program = $this->makeProgram('draft');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.launch', [self::TENANT_ID, $program->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('programs', [
            'id'     => $program->id,
            'status' => 'active',
        ]);
    }

    public function test_owner_can_pause_active_program(): void
    {
        $program = $this->makeProgram('active');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.pause', [self::TENANT_ID, $program->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('programs', [
            'id'     => $program->id,
            'status' => 'paused',
        ]);
    }

    public function test_illegal_status_transition_returns_error(): void
    {
        // archived → active is not allowed
        $program = $this->makeProgram('archived');

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.launch', [self::TENANT_ID, $program->id]))
            ->assertRedirect()
            ->assertSessionHasErrors();

        // Status unchanged
        $this->assertDatabaseHas('programs', [
            'id'     => $program->id,
            'status' => 'archived',
        ]);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function test_owner_can_delete_draft_program(): void
    {
        $program = $this->makeProgram('draft');
        $id      = $program->id;

        $this->actingAs($this->ownerUser, 'tenant')
            ->delete(route('tenant.programs.destroy', [self::TENANT_ID, $id]))
            ->assertRedirect(route('tenant.programs.index', self::TENANT_ID));

        $this->assertSoftDeleted('programs', ['id' => $id]);
    }

    public function test_active_program_cannot_be_deleted(): void
    {
        $program = $this->makeProgram('active');

        $this->actingAs($this->ownerUser, 'tenant')
            ->delete(route('tenant.programs.destroy', [self::TENANT_ID, $program->id]))
            ->assertStatus(403);

        $this->assertDatabaseHas('programs', [
            'id'         => $program->id,
            'deleted_at' => null,
        ]);
    }

    // ── Schema / Fixtures ─────────────────────────────────────────────────────

    private function makeProgram(string $status = 'draft'): Program
    {
        return Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Test Program ' . Str::random(4),
            'program_type' => 'referral',
            'status'       => $status,
        ]);
    }

    private function buildSchema(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
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
            $table->boolean('is_default')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('public_visibility')->default('private');
            $table->string('short_description')->nullable();
            $table->text('full_description')->nullable();
            $table->string('application_mode')->default('invite_only');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('launched_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('program_configuration_versions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->unsignedInteger('version_number')->default(1);
            $table->string('status')->default('published');
            $table->timestamp('published_at')->nullable();
            $table->string('published_by')->nullable();
            $table->json('snapshot')->nullable();
            $table->string('change_summary')->nullable();
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

        Schema::create('leads', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('description')->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
    }

    private function dropSchema(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('tenant_brand_profiles');
        Schema::dropIfExists('program_configuration_versions');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }

    private function seedFixtures(): void
    {
        $this->tenant = Tenant::create([
            'id'     => self::TENANT_ID,
            'name'   => 'Test Programs Tenant',
            'status' => 'active',
        ]);

        $this->otherTenant = Tenant::create([
            'id'     => self::OTHER_TENANT,
            'name'   => 'Other Tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@programs-test.com',
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
            'email'    => 'manager@programs-test.com',
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

        $this->viewerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'viewer@programs-test.com',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        TenantMembership::create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => self::TENANT_ID,
            'tenant_user_id' => $this->viewerUser->id,
            'role'           => 'viewer',
            'status'         => 'active',
        ]);
    }
}
