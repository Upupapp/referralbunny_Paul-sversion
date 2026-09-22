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
 * Feature tests for the Programs V4 "Public Page" tab — live-URL preview
 * plus a custom call-to-action text field on the existing public program
 * page.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramPublicPageTabTest extends TestCase
{
    private const TENANT_ID = 'test-public-page-tenant';

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

    public function test_owner_can_save_public_cta_text(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->patch(route('tenant.programs.update', [self::TENANT_ID, $this->program->id]), [
                'public_cta_text' => 'Apply now — spots are limited!',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('programs', [
            'id'               => $this->program->id,
            'public_cta_text'  => 'Apply now — spots are limited!',
        ]);
    }

    public function test_manager_without_manage_programs_cannot_save_public_cta_text(): void
    {
        $this->actingAs($this->managerUser, 'tenant')
            ->patch(route('tenant.programs.update', [self::TENANT_ID, $this->program->id]), [
                'public_cta_text' => 'Apply now!',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('programs', [
            'id'              => $this->program->id,
            'public_cta_text' => 'Apply now!',
        ]);
    }

    public function test_public_cta_text_over_160_chars_is_rejected(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->patch(route('tenant.programs.update', [self::TENANT_ID, $this->program->id]), [
                'public_cta_text' => str_repeat('a', 161),
            ])
            ->assertSessionHasErrors('public_cta_text');
    }

    public function test_get_workspace_public_page_tab_renders_with_preview_url(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=public-page')
            ->assertStatus(200)
            ->assertSee(route('public.programs.show', [$this->tenant->slug, $this->program->slug]), false);
    }

    public function test_get_workspace_other_tabs_render_without_error(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=overview')
            ->assertStatus(200);
    }

    // ── Schema / Fixtures ─────────────────────────────────────────────────────

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
            $table->string('operating_mode')->default('manual');
            $table->string('status')->default('draft');
            $table->string('public_visibility')->default('private');
            $table->string('public_cta_text')->nullable();
            $table->string('application_mode')->default('invite_only');
            $table->string('default_currency')->default('PHP');
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
            'name'   => 'Test Public Page Tenant',
            'slug'   => 'test-public-page-tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@public-page-test.com',
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
            'email'    => 'manager@public-page-test.com',
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
            'tenant_id'         => self::TENANT_ID,
            'name'              => 'Test Program',
            'slug'              => 'test-program',
            'program_type'      => 'referral',
            'status'            => 'active',
            'public_visibility' => 'public',
        ]);
    }
}
