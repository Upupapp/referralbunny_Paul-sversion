<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the ProgramPolicy guardrail blocking ProtectedTenants
 * (lgu-ids) from Programs V4 entirely, regardless of role or permission --
 * PROGRAMS_V4_ENABLED is a global, not per-tenant, flag, so this is the
 * single choke point that keeps a protected tenant out if the flag is ever
 * turned on.
 *
 * Uses the real 'lgu-ids' tenant id (matching App\Support\ProtectedTenants)
 * against a local sqlite schema -- does NOT touch the actual production
 * lgu-ids tenant or its data.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramProtectedTenantTest extends TestCase
{
    private const PROTECTED_TENANT_ID = 'lgu-ids';
    private const NORMAL_TENANT_ID    = 'test-protected-normal-tenant';

    private TenantUser $protectedOwnerUser;
    private TenantUser $normalOwnerUser;
    private User       $superAdminUser;
    private Program    $protectedProgram;

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

    public function test_lgu_ids_owner_cannot_view_programs_index(): void
    {
        $this->actingAs($this->protectedOwnerUser, 'tenant')
            ->get(route('tenant.programs.index', self::PROTECTED_TENANT_ID))
            ->assertStatus(403);
    }

    public function test_lgu_ids_owner_cannot_create_program(): void
    {
        $this->actingAs($this->protectedOwnerUser, 'tenant')
            ->post(route('tenant.programs.store', self::PROTECTED_TENANT_ID), [
                'name'         => 'Should Not Be Created',
                'program_type' => 'referral',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('programs', ['name' => 'Should Not Be Created']);
    }

    public function test_lgu_ids_owner_cannot_view_existing_program_workspace(): void
    {
        $this->actingAs($this->protectedOwnerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::PROTECTED_TENANT_ID, $this->protectedProgram->id]))
            ->assertStatus(403);
    }

    public function test_super_admin_cannot_view_lgu_ids_program_workspace(): void
    {
        // The guardrail must block even the platform Super Admin (web guard),
        // who otherwise bypasses every other ProgramPolicy check unconditionally.
        $this->actingAs($this->superAdminUser, 'web')
            ->get(route('tenant.programs.workspace', [self::PROTECTED_TENANT_ID, $this->protectedProgram->id]))
            ->assertStatus(403);
    }

    public function test_super_admin_unaffected_by_guardrail_on_normal_tenant(): void
    {
        $program = Program::create([
            'tenant_id'    => self::NORMAL_TENANT_ID,
            'name'         => 'Normal Program For SA',
            'program_type' => 'referral',
            'status'       => 'draft',
        ]);

        $this->actingAs($this->superAdminUser, 'web')
            ->get(route('tenant.programs.workspace', [self::NORMAL_TENANT_ID, $program->id]))
            ->assertStatus(200);
    }

    public function test_normal_tenant_owner_unaffected_by_guardrail(): void
    {
        $program = Program::create([
            'tenant_id'    => self::NORMAL_TENANT_ID,
            'name'         => 'Normal Program',
            'program_type' => 'referral',
            'status'       => 'draft',
        ]);

        $this->actingAs($this->normalOwnerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::NORMAL_TENANT_ID, $program->id]))
            ->assertStatus(200);
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

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
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
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }

    private function seedFixtures(): void
    {
        Tenant::create(['id' => self::PROTECTED_TENANT_ID, 'name' => 'LGU IDS', 'status' => 'active']);
        Tenant::create(['id' => self::NORMAL_TENANT_ID, 'name' => 'Normal Tenant', 'status' => 'active']);

        $this->protectedOwnerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@protected-test.com',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        TenantMembership::create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => self::PROTECTED_TENANT_ID,
            'tenant_user_id' => $this->protectedOwnerUser->id,
            'role'           => 'owner',
            'status'         => 'active',
        ]);

        $this->normalOwnerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@normal-test.com',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        TenantMembership::create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => self::NORMAL_TENANT_ID,
            'tenant_user_id' => $this->normalOwnerUser->id,
            'role'           => 'owner',
            'status'         => 'active',
        ]);

        $this->superAdminUser = User::create([
            'name'     => 'Test Super Admin',
            'email'    => 'superadmin@protected-test.com',
            'password' => bcrypt('password'),
        ]);

        $this->protectedProgram = Program::create([
            'tenant_id'    => self::PROTECTED_TENANT_ID,
            'name'         => 'Pre-existing LGU IDS Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);
    }
}
