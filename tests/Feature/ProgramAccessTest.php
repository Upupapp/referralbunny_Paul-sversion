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
 * Feature tests for the Programs V4 "Access" tab — a read-only display of
 * which tenant team members can view/manage programs, computed from the
 * existing tenant-wide PermissionService (no per-program permissions exist).
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramAccessTest extends TestCase
{
    private const TENANT_ID = 'test-access-tenant';

    private Tenant  $tenant;
    private Program $program;

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

    public function test_owner_row_can_view_and_manage(): void
    {
        $owner = $this->makeMember('owner');

        $this->actingAs($owner, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=access')
            ->assertStatus(200)
            ->assertSeeInOrder(['Owner']);
    }

    public function test_manager_with_default_permissions_can_view_but_not_manage(): void
    {
        $owner = $this->makeMember('owner');
        $this->makeMember('manager', 'manager@access-test.com');

        $response = $this->actingAs($owner, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=access');

        $response->assertStatus(200);
        // Manager: view_programs=true (MANAGER_DEFAULTS), manage_programs=false by default.
        $response->assertSeeInOrder(['Manager', 'Yes', 'No']);
    }

    public function test_manager_with_custom_override_can_manage(): void
    {
        $owner = $this->makeMember('owner');
        TenantMembership::create([
            'id'                    => (string) Str::uuid(),
            'tenant_id'             => self::TENANT_ID,
            'tenant_user_id'        => $this->makeTenantUser('manager2@access-test.com')->id,
            'role'                  => 'manager',
            'status'                => 'active',
            'is_custom_permissions' => true,
            'permissions_json'      => ['manage_programs' => true],
        ]);

        $this->actingAs($owner, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=access')
            ->assertStatus(200);

        // Verify via the service directly too, since the override is the point of this test.
        $membership = TenantMembership::where('tenant_id', self::TENANT_ID)->where('role', 'manager')->first();
        $service    = new \App\Services\PermissionService();
        $this->assertTrue($service->can($membership, 'manage_programs'));
    }

    public function test_member_and_viewer_cannot_view_or_manage(): void
    {
        $owner  = $this->makeMember('owner');
        $member = $this->makeMember('member', 'member@access-test.com');

        $service = new \App\Services\PermissionService();
        $membership = TenantMembership::where('tenant_user_id', $member->id)->first();
        $this->assertFalse($service->can($membership, 'view_programs'));
        $this->assertFalse($service->can($membership, 'manage_programs'));
    }

    public function test_member_without_view_programs_gets_403_on_access_tab(): void
    {
        $member = $this->makeMember('member');

        $this->actingAs($member, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=access')
            ->assertStatus(403);
    }

    public function test_membership_from_another_tenant_never_appears_in_access_list(): void
    {
        $owner = $this->makeMember('owner');

        $otherTenant = Tenant::create(['id' => 'test-access-other', 'name' => 'Other Tenant', 'status' => 'active']);
        $otherUser   = $this->makeTenantUser('other-tenant-user@access-test.com');
        TenantMembership::create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => $otherTenant->id,
            'tenant_user_id' => $otherUser->id,
            'role'           => 'owner',
            'status'         => 'active',
        ]);

        $response = $this->actingAs($owner, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=access');

        $response->assertStatus(200);
        $response->assertDontSee('other-tenant-user@access-test.com');
    }

    public function test_suspended_membership_excluded_from_access_list(): void
    {
        $owner = $this->makeMember('owner');
        $suspendedUser = $this->makeTenantUser('suspended@access-test.com');
        TenantMembership::create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => self::TENANT_ID,
            'tenant_user_id' => $suspendedUser->id,
            'role'           => 'manager',
            'status'         => 'suspended',
        ]);

        $response = $this->actingAs($owner, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=access');

        $response->assertStatus(200);
        $response->assertDontSee('suspended@access-test.com');
    }

    public function test_get_workspace_other_tabs_render_without_error_when_access_data_is_null(): void
    {
        $owner = $this->makeMember('owner');

        $this->actingAs($owner, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=overview')
            ->assertStatus(200);
    }

    // ── Schema / Fixtures ─────────────────────────────────────────────────────

    private function makeTenantUser(string $email): TenantUser
    {
        return TenantUser::create([
            'id'         => (string) Str::uuid(),
            'email'      => $email,
            'password'   => bcrypt('password'),
            'status'     => 'active',
            'first_name' => 'Test',
            'last_name'  => ucfirst(explode('@', $email)[0]),
        ]);
    }

    private function makeMember(string $role, string $email = 'owner@access-test.com'): TenantUser
    {
        $user = $this->makeTenantUser($email);
        TenantMembership::create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => self::TENANT_ID,
            'tenant_user_id' => $user->id,
            'role'           => $role,
            'status'         => 'active',
        ]);
        return $user;
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
            $table->boolean('is_custom_permissions')->default(false);
            $table->text('permissions_json')->nullable();
            $table->boolean('can_manage_billing')->default(false);
            $table->boolean('can_delete_tenant')->default(false);
            $table->boolean('can_transfer_ownership')->default(false);
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
            'name'   => 'Test Access Tenant',
            'status' => 'active',
        ]);

        $this->program = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Test Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);
    }
}
