<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerProgramMembership;
use App\Models\Program;
use App\Models\ReferrerProgramMembership;
use App\Models\Reseller;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the ProtectedTenants guard added to
 * ReferrerProgramController and PartnerProgramController -- these portal
 * controllers never go through ProgramPolicy (by design, see that class's
 * doc-block), so they need their own copy of the lgu-ids guardrail.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramReferrerPartnerProtectedTenantTest extends TestCase
{
    private const PROTECTED_TENANT_ID = 'lgu-ids';
    private const NORMAL_TENANT_ID    = 'test-rp-protected-normal-tenant';

    private Reseller $protectedReferrer;
    private Reseller $normalReferrer;
    private Partner  $protectedPartner;
    private Partner  $normalPartner;
    private Program  $protectedProgram;
    private Program  $normalProgram;

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

    public function test_referrer_index_blocked_for_protected_tenant(): void
    {
        $this->actingAs($this->protectedReferrer, 'reseller')
            ->get(route('reseller.programs.index', self::PROTECTED_TENANT_ID))
            ->assertStatus(404);
    }

    public function test_referrer_show_blocked_for_protected_tenant(): void
    {
        $this->actingAs($this->protectedReferrer, 'reseller')
            ->get(route('reseller.programs.show', [self::PROTECTED_TENANT_ID, $this->protectedProgram->id]))
            ->assertStatus(404);
    }

    public function test_referrer_unaffected_on_normal_tenant(): void
    {
        $this->actingAs($this->normalReferrer, 'reseller')
            ->get(route('reseller.programs.index', self::NORMAL_TENANT_ID))
            ->assertStatus(200);
    }

    public function test_partner_index_blocked_for_protected_tenant(): void
    {
        $this->actingAs($this->protectedPartner, 'partner')
            ->get(route('partner.programs.index'))
            ->assertStatus(404);
    }

    public function test_partner_show_blocked_for_protected_tenant(): void
    {
        $this->actingAs($this->protectedPartner, 'partner')
            ->get(route('partner.programs.show', $this->protectedProgram->id))
            ->assertStatus(404);
    }

    public function test_partner_unaffected_on_normal_tenant(): void
    {
        $this->actingAs($this->normalPartner, 'partner')
            ->get(route('partner.programs.index'))
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

        Schema::create('resellers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('status')->default('active');
            $table->string('remember_token')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('status')->default('active');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('remember_token')->nullable();
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('program_type')->default('referral');
            $table->string('status')->default('draft');
            $table->string('public_visibility')->default('private');
            $table->string('default_currency')->default('PHP');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('referrer_program_memberships', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('reseller_id');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('partner_program_memberships', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('partner_id');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        // Layout dependencies for the 200-path tests, which render the full
        // reseller/partner portal layout (nav badges, branding), not just
        // the controller's own view.
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

        Schema::create('partner_threads', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('partner_id');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
    }

    private function dropSchema(): void
    {
        Schema::dropIfExists('partner_threads');
        Schema::dropIfExists('tenant_brand_profiles');
        Schema::dropIfExists('partner_program_memberships');
        Schema::dropIfExists('referrer_program_memberships');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('partner_users');
        Schema::dropIfExists('resellers');
        Schema::dropIfExists('tenants');
    }

    private function seedFixtures(): void
    {
        Tenant::create(['id' => self::PROTECTED_TENANT_ID, 'name' => 'LGU IDS', 'status' => 'active']);
        Tenant::create(['id' => self::NORMAL_TENANT_ID, 'name' => 'Normal Tenant', 'status' => 'active']);

        $this->protectedReferrer = Reseller::create([
            'id'        => (string) Str::uuid(),
            'tenant_id' => self::PROTECTED_TENANT_ID,
            'name'      => 'Protected Referrer',
            'email'     => 'referrer@protected-rp-test.com',
            'password'  => bcrypt('password'),
            'status'    => 'active',
        ]);

        $this->normalReferrer = Reseller::create([
            'id'        => (string) Str::uuid(),
            'tenant_id' => self::NORMAL_TENANT_ID,
            'name'      => 'Normal Referrer',
            'email'     => 'referrer@normal-rp-test.com',
            'password'  => bcrypt('password'),
            'status'    => 'active',
        ]);

        $this->protectedPartner = Partner::create([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => self::PROTECTED_TENANT_ID,
            'email'      => 'partner@protected-rp-test.com',
            'password'   => bcrypt('password'),
            'status'     => 'active',
            'first_name' => 'Protected',
            'last_name'  => 'Partner',
        ]);

        $this->normalPartner = Partner::create([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => self::NORMAL_TENANT_ID,
            'email'      => 'partner@normal-rp-test.com',
            'password'   => bcrypt('password'),
            'status'     => 'active',
            'first_name' => 'Normal',
            'last_name'  => 'Partner',
        ]);

        $this->protectedProgram = Program::create([
            'tenant_id'    => self::PROTECTED_TENANT_ID,
            'name'         => 'Protected Tenant Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);

        $this->normalProgram = Program::create([
            'tenant_id'    => self::NORMAL_TENANT_ID,
            'name'         => 'Normal Tenant Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);

        ReferrerProgramMembership::create([
            'tenant_id'   => self::PROTECTED_TENANT_ID,
            'program_id'  => $this->protectedProgram->id,
            'reseller_id' => $this->protectedReferrer->id,
            'status'      => 'active',
        ]);

        ReferrerProgramMembership::create([
            'tenant_id'   => self::NORMAL_TENANT_ID,
            'program_id'  => $this->normalProgram->id,
            'reseller_id' => $this->normalReferrer->id,
            'status'      => 'active',
        ]);

        PartnerProgramMembership::create([
            'tenant_id'  => self::PROTECTED_TENANT_ID,
            'program_id' => $this->protectedProgram->id,
            'partner_id' => $this->protectedPartner->id,
            'status'     => 'active',
        ]);

        PartnerProgramMembership::create([
            'tenant_id'  => self::NORMAL_TENANT_ID,
            'program_id' => $this->normalProgram->id,
            'partner_id' => $this->normalPartner->id,
            'status'     => 'active',
        ]);
    }
}
