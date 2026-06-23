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
 * Feature tests for the new program_id scoping added to the existing
 * tenant-wide request-form system (RequestFormController::create()/store()).
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class RequestFormProgramScopingTest extends TestCase
{
    private const TENANT_ID        = 'test-rf-scoping-tenant';
    private const OTHER_TENANT     = 'test-rf-scoping-other';
    private const PROTECTED_TENANT = 'lgu-ids';

    private Tenant     $tenant;
    private TenantUser $ownerUser;
    private Program    $program;
    private TenantUser $protectedOwnerUser;
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

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'title'  => 'Test Intake Form',
            'fields' => [
                ['label' => 'Email', 'field_key' => 'email', 'field_type' => 'email', 'is_required' => true],
            ],
        ], $overrides);
    }

    public function test_creating_form_with_valid_program_id_for_this_tenant_persists_it(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.request-forms.store', self::TENANT_ID), $this->storePayload([
                'program_id' => $this->program->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('request_forms', [
            'tenant_id'  => self::TENANT_ID,
            'title'      => 'Test Intake Form',
            'program_id' => $this->program->id,
        ]);
    }

    public function test_program_id_belonging_to_another_tenant_is_rejected(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::OTHER_TENANT,
            'name'         => 'Other Tenant Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.request-forms.store', self::TENANT_ID), $this->storePayload([
                'program_id' => $otherProgram->id,
            ]))
            ->assertStatus(404);

        $this->assertDatabaseMissing('request_forms', [
            'title' => 'Test Intake Form',
        ]);
    }

    public function test_archived_program_id_is_rejected_on_store(): void
    {
        $this->program->update(['status' => 'archived']);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.request-forms.store', self::TENANT_ID), $this->storePayload([
                'program_id' => $this->program->id,
            ]))
            ->assertStatus(404);

        $this->assertDatabaseMissing('request_forms', [
            'title' => 'Test Intake Form',
        ]);
    }

    public function test_archived_program_id_is_rejected_on_create_page(): void
    {
        $this->program->update(['status' => 'archived']);

        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.request-forms.create', self::TENANT_ID) . '?program_id=' . $this->program->id)
            ->assertStatus(404);
    }

    public function test_protected_tenant_program_id_is_rejected_on_store(): void
    {
        // RequestFormController predates Programs V4's ProtectedTenants
        // guardrail in ProgramPolicy -- this is the same choke point applied
        // to this side door, so an lgu-ids program_id must be rejected here too.
        $this->actingAs($this->protectedOwnerUser, 'tenant')
            ->post(route('tenant.request-forms.store', self::PROTECTED_TENANT), $this->storePayload([
                'program_id' => $this->protectedProgram->id,
            ]))
            ->assertStatus(404);

        $this->assertDatabaseMissing('request_forms', [
            'title' => 'Test Intake Form',
        ]);
    }

    public function test_protected_tenant_program_id_is_rejected_on_create_page(): void
    {
        $this->actingAs($this->protectedOwnerUser, 'tenant')
            ->get(route('tenant.request-forms.create', self::PROTECTED_TENANT) . '?program_id=' . $this->protectedProgram->id)
            ->assertStatus(404);
    }

    public function test_creating_form_with_no_program_id_still_works_as_general_tenant_form(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.request-forms.store', self::TENANT_ID), $this->storePayload())
            ->assertRedirect();

        $this->assertDatabaseHas('request_forms', [
            'tenant_id'  => self::TENANT_ID,
            'title'      => 'Test Intake Form',
            'program_id' => null,
        ]);
    }

    public function test_create_page_with_valid_program_id_query_param_renders(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.request-forms.create', self::TENANT_ID) . '?program_id=' . $this->program->id)
            ->assertStatus(200);
    }

    public function test_create_page_with_program_id_from_another_tenant_404s(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::OTHER_TENANT,
            'name'         => 'Other Tenant Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.request-forms.create', self::TENANT_ID) . '?program_id=' . $otherProgram->id)
            ->assertStatus(404);
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
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
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

        Schema::create('request_form_fields', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('request_form_id');
            $table->string('label');
            $table->string('field_key');
            $table->string('field_type')->default('text');
            $table->string('placeholder')->nullable();
            $table->text('helper_text')->nullable();
            $table->text('options')->nullable();
            $table->text('validation_rules')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system_field')->default(false);
            $table->timestamps();
        });

        Schema::create('request_form_recipient_options', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('request_form_id');
            $table->string('recipient_type');
            $table->string('recipient_id')->nullable();
            $table->string('display_name');
            $table->string('email');
            $table->string('role_snapshot')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
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
        Schema::dropIfExists('request_form_recipient_options');
        Schema::dropIfExists('request_form_fields');
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
            'name'   => 'Test RF Scoping Tenant',
            'status' => 'active',
        ]);

        Tenant::create([
            'id'     => self::OTHER_TENANT,
            'name'   => 'Other Tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@rf-scoping-test.com',
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

        Tenant::create([
            'id'     => self::PROTECTED_TENANT,
            'name'   => 'LGU IDS',
            'status' => 'active',
        ]);

        $this->protectedOwnerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@protected-rf-scoping-test.com',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        TenantMembership::create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => self::PROTECTED_TENANT,
            'tenant_user_id' => $this->protectedOwnerUser->id,
            'role'           => 'owner',
            'status'         => 'active',
        ]);

        $this->protectedProgram = Program::create([
            'tenant_id'    => self::PROTECTED_TENANT,
            'name'         => 'Protected Tenant Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);
    }
}
