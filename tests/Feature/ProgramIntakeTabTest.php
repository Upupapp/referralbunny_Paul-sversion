<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\RequestForm;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the Programs V4 "Intake" tab — a thin, filtered view
 * into the existing tenant-wide request-form system, scoped by the new
 * request_forms.program_id column.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramIntakeTabTest extends TestCase
{
    private const TENANT_ID = 'test-intake-tenant';

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

    public function test_intake_tab_lists_only_this_programs_forms(): void
    {
        $this->makeForm('This Program Form', $this->program->id);
        $otherProgram = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Other Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);
        $this->makeForm('Other Program Form', $otherProgram->id);
        $this->makeForm('General Tenant Form', null);

        $response = $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=intake');

        $response->assertStatus(200);
        $response->assertSee('This Program Form');
        $response->assertDontSee('Other Program Form');
        $response->assertDontSee('General Tenant Form');
    }

    public function test_get_workspace_other_tabs_render_without_error(): void
    {
        $this->makeForm('Some Form', $this->program->id);

        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=overview')
            ->assertStatus(200);
    }

    // ── Schema / Fixtures ─────────────────────────────────────────────────────

    private function makeForm(string $title, ?string $programId): RequestForm
    {
        return RequestForm::create([
            'tenant_id'  => self::TENANT_ID,
            'program_id' => $programId,
            'title'      => $title,
            'status'     => 'published',
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

        Schema::create('request_forms', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id')->nullable();
            $table->string('created_by_type')->nullable();
            $table->string('created_by_id')->nullable();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('public_token', 64)->nullable();
            $table->text('description')->nullable();
            $table->text('success_message')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_public')->default(true);
            $table->boolean('allow_multiple_recipients')->default(true);
            $table->integer('max_recipients')->default(5);
            $table->text('settings')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('request_form_submissions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('request_form_id');
            $table->string('public_submission_uuid', 36)->nullable();
            $table->string('submitter_name')->nullable();
            $table->string('submitter_email')->nullable();
            $table->string('request_for')->nullable();
            $table->text('notes')->nullable();
            $table->text('payload')->nullable();
            $table->text('selected_recipient_ids')->nullable();
            $table->string('status')->default('received');
            $table->timestamp('submitted_at')->nullable();
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
    }

    private function dropSchema(): void
    {
        Schema::dropIfExists('tenant_brand_profiles');
        Schema::dropIfExists('request_form_submissions');
        Schema::dropIfExists('request_forms');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }

    private function seedFixtures(): void
    {
        $this->tenant = Tenant::create([
            'id'     => self::TENANT_ID,
            'name'   => 'Test Intake Tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@intake-test.com',
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
