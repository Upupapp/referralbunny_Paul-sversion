<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Feature tests for the unauthenticated public program page
 * (PublicProgramController@show, /p/{tenantSlug}/{programSlug}).
 *
 * No prior test coverage existed for this controller/route at all before
 * this phase — these tests cover both the pre-existing visibility/status
 * gating and the new custom CTA text behavior added in this phase.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class PublicProgramShowTest extends TestCase
{
    private const TENANT_ID = 'test-public-show-tenant';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('programs.enabled', true);
        $this->buildSchema();
        $this->tenant = Tenant::create([
            'id'     => self::TENANT_ID,
            'name'   => 'Test Public Show Tenant',
            'slug'   => 'test-public-show-tenant',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    public function test_custom_cta_text_renders_when_set(): void
    {
        $program = $this->makeProgram([
            'public_cta_text' => 'Apply now — spots are limited!',
        ]);

        $this->get(route('public.programs.show', [$this->tenant->slug, $program->slug]))
            ->assertStatus(200)
            ->assertSee('Apply now — spots are limited!')
            ->assertDontSee('Interested in joining? Contact');
    }

    public function test_generic_fallback_text_renders_when_cta_text_is_null(): void
    {
        $program = $this->makeProgram(['public_cta_text' => null]);

        $this->get(route('public.programs.show', [$this->tenant->slug, $program->slug]))
            ->assertStatus(200)
            ->assertSee('Interested in joining? Contact ' . $this->tenant->name . ' to learn more.');
    }

    public function test_draft_program_returns_404(): void
    {
        $program = $this->makeProgram(['status' => 'draft', 'public_visibility' => 'public']);

        $this->get(route('public.programs.show', [$this->tenant->slug, $program->slug]))
            ->assertStatus(404);
    }

    public function test_private_program_returns_404(): void
    {
        $program = $this->makeProgram(['status' => 'active', 'public_visibility' => 'private']);

        $this->get(route('public.programs.show', [$this->tenant->slug, $program->slug]))
            ->assertStatus(404);
    }

    public function test_protected_tenant_program_returns_404(): void
    {
        // PublicProgramController is fully unauthenticated and never goes
        // through ProgramPolicy, so it needs its own ProtectedTenants check --
        // lgu-ids's slug is the literal, well-known string 'lgu-ids', making
        // this route trivially guessable if unguarded.
        $protectedTenant = Tenant::create([
            'id'     => 'lgu-ids',
            'name'   => 'LGU IDS',
            'slug'   => 'lgu-ids',
            'status' => 'active',
        ]);

        $program = Program::create([
            'tenant_id'         => 'lgu-ids',
            'name'              => 'Protected Tenant Program',
            'slug'              => 'protected-program',
            'program_type'      => 'referral',
            'status'            => 'active',
            'public_visibility' => 'public',
            'application_mode'  => 'application',
        ]);

        $this->get(route('public.programs.show', [$protectedTenant->slug, $program->slug]))
            ->assertStatus(404);
    }

    // ── Schema / Fixtures ─────────────────────────────────────────────────────

    private function makeProgram(array $overrides = []): Program
    {
        return Program::create(array_merge([
            'tenant_id'         => self::TENANT_ID,
            'name'              => 'Test Program',
            'slug'              => 'test-program-' . \Illuminate\Support\Str::random(6),
            'program_type'      => 'referral',
            'status'            => 'active',
            'public_visibility' => 'public',
            'application_mode'  => 'application',
        ], $overrides));
    }

    private function buildSchema(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('program_type')->default('referral');
            $table->string('status')->default('draft');
            $table->text('short_description')->nullable();
            $table->text('full_description')->nullable();
            $table->string('public_visibility')->default('private');
            $table->string('public_cta_text')->nullable();
            $table->string('application_mode')->default('invite_only');
            $table->timestamp('starts_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function dropSchema(): void
    {
        Schema::dropIfExists('programs');
        Schema::dropIfExists('tenants');
    }
}
