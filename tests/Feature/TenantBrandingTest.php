<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantBrandProfile;
use App\Models\TenantBrandVersion;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for Tenant Brand Studio.
 *
 * Does NOT use RefreshDatabase (same SQLite/PG incompatibility).
 * Builds and tears down only the tables it needs.
 */
class TenantBrandingTest extends TestCase
{
    private const TENANT_ID = 'test-brand-tenant';

    private Tenant       $tenant;
    private TenantUser   $ownerUser;
    private TenantUser   $managerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    // ── Health Score ──────────────────────────────────────────────────────────

    public function test_health_score_zero_with_no_brand(): void
    {
        $score = TenantBrandProfile::computeHealthScore($this->tenant, null);
        $this->assertSame(0, $score);
    }

    public function test_health_score_with_all_fields(): void
    {
        $profile = new TenantBrandProfile([
            'tenant_id'    => self::TENANT_ID,
            'logo_url'     => 'https://example.com/logo.png',
            'accent_color' => '#123456',
            'sidebar_color'=> '#654321',
            'status'       => 'draft',
        ]);

        $this->tenant->program_name  = 'My Program';
        $this->tenant->business_name = 'My Business';

        $score = TenantBrandProfile::computeHealthScore($this->tenant, $profile);
        $this->assertSame(100, $score);
    }

    public function test_health_score_logo_only(): void
    {
        $profile = new TenantBrandProfile([
            'logo_url' => 'https://example.com/logo.png',
        ]);
        $score = TenantBrandProfile::computeHealthScore($this->tenant, $profile);
        $this->assertSame(30, $score);
    }

    // ── WCAG Contrast ─────────────────────────────────────────────────────────

    public function test_wcag_passes_for_dark_color(): void
    {
        $this->assertTrue(TenantBrandProfile::passesWcagAa('#1a1a1a'));
    }

    public function test_wcag_fails_for_light_color(): void
    {
        $this->assertFalse(TenantBrandProfile::passesWcagAa('#FFFF00'));
    }

    // ── Save Draft ────────────────────────────────────────────────────────────

    public function test_owner_can_save_draft(): void
    {
        $response = $this->actingAs($this->ownerUser, 'tenant')
            ->postJson(route('tenant.settings.branding.save-draft', self::TENANT_ID), [
                'accent_color' => '#123456',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('tenant_brand_profiles', [
            'tenant_id'    => self::TENANT_ID,
            'accent_color' => '#123456',
        ]);
    }

    public function test_manager_cannot_save_draft(): void
    {
        $response = $this->actingAs($this->managerUser, 'tenant')
            ->postJson(route('tenant.settings.branding.save-draft', self::TENANT_ID), [
                'accent_color' => '#123456',
            ]);

        $response->assertStatus(403);
    }

    public function test_draft_rejects_invalid_hex_color(): void
    {
        $response = $this->actingAs($this->ownerUser, 'tenant')
            ->postJson(route('tenant.settings.branding.save-draft', self::TENANT_ID), [
                'accent_color' => 'notahex',
            ]);

        $response->assertStatus(422);
    }

    // ── Publish ───────────────────────────────────────────────────────────────

    public function test_publish_writes_to_tenants_table(): void
    {
        // First save a draft
        TenantBrandProfile::updateOrCreate(
            ['tenant_id' => self::TENANT_ID],
            ['accent_color' => '#ABCDEF', 'status' => 'draft']
        );

        $response = $this->actingAs($this->ownerUser, 'tenant')
            ->postJson(route('tenant.settings.branding.publish', self::TENANT_ID));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('tenant_brand_profiles', [
            'tenant_id' => self::TENANT_ID,
            'status'    => 'published',
        ]);
        $this->assertDatabaseHas('tenants', [
            'id'           => self::TENANT_ID,
            'accent_color' => '#ABCDEF',
        ]);
    }

    public function test_publish_fails_with_no_draft(): void
    {
        $response = $this->actingAs($this->ownerUser, 'tenant')
            ->postJson(route('tenant.settings.branding.publish', self::TENANT_ID));

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Nothing to publish — save a draft first.']);
    }

    // ── Logo Upload ───────────────────────────────────────────────────────────

    public function test_logo_upload_accepts_valid_png(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->actingAs($this->ownerUser, 'tenant')
            ->postJson(route('tenant.settings.branding.upload-logo', self::TENANT_ID), [
                'logo' => $file,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'logo_url']);
    }

    public function test_logo_upload_rejects_oversized_file(): void
    {
        Storage::fake('public');

        // Create a file just over 2 MB
        $file = UploadedFile::fake()->create('logo.png', 2049, 'image/png');

        $response = $this->actingAs($this->ownerUser, 'tenant')
            ->postJson(route('tenant.settings.branding.upload-logo', self::TENANT_ID), [
                'logo' => $file,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('2 MB', $response->json('error') ?? '');
    }

    // ── Version History (Phase 2) ─────────────────────────────────────────────

    public function test_publish_creates_version_snapshot(): void
    {
        TenantBrandProfile::updateOrCreate(
            ['tenant_id' => self::TENANT_ID],
            ['accent_color' => '#112233', 'status' => 'draft']
        );

        $this->actingAs($this->ownerUser, 'tenant')
            ->postJson(route('tenant.settings.branding.publish', self::TENANT_ID))
            ->assertStatus(200);

        $this->assertDatabaseHas('tenant_brand_versions', [
            'tenant_id'    => self::TENANT_ID,
            'accent_color' => '#112233',
        ]);
    }

    public function test_versions_endpoint_returns_list(): void
    {
        TenantBrandVersion::create([
            'tenant_id'    => self::TENANT_ID,
            'accent_color' => '#AABBCC',
            'health_score' => 55,
            'created_at'   => now(),
        ]);

        $response = $this->actingAs($this->ownerUser, 'tenant')
            ->getJson(route('tenant.settings.branding.versions', self::TENANT_ID));

        $response->assertStatus(200);
        $response->assertJsonStructure([['id', 'accent_color', 'health_score', 'created_at']]);
        $this->assertSame('#AABBCC', $response->json('0.accent_color'));
    }

    public function test_restore_version_sets_draft(): void
    {
        $version = TenantBrandVersion::create([
            'tenant_id'    => self::TENANT_ID,
            'accent_color' => '#DDEEFF',
            'sidebar_color'=> '#001122',
            'health_score' => 75,
            'created_at'   => now(),
        ]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->postJson(route('tenant.settings.branding.restore-version', [self::TENANT_ID, $version->id]))
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tenant_brand_profiles', [
            'tenant_id'    => self::TENANT_ID,
            'accent_color' => '#DDEEFF',
            'sidebar_color'=> '#001122',
            'status'       => 'draft',
        ]);
    }

    public function test_publish_trims_versions_to_ten(): void
    {
        // Pre-seed 10 existing versions
        for ($i = 0; $i < 10; $i++) {
            TenantBrandVersion::create([
                'tenant_id'    => self::TENANT_ID,
                'health_score' => 50,
                'created_at'   => now()->subMinutes(10 - $i),
            ]);
        }

        // Publish to trigger the 11th snapshot + trim
        TenantBrandProfile::updateOrCreate(
            ['tenant_id' => self::TENANT_ID],
            ['accent_color' => '#FFFFFF', 'status' => 'draft']
        );

        $this->actingAs($this->ownerUser, 'tenant')
            ->postJson(route('tenant.settings.branding.publish', self::TENANT_ID))
            ->assertStatus(200);

        $this->assertSame(
            10,
            TenantBrandVersion::where('tenant_id', self::TENANT_ID)->count()
        );
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    private function buildSchema(): void
    {
        $this->dropSchema();

        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('program_name')->nullable();
            $table->string('business_name')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('accent_color', 7)->nullable();
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
            $table->timestamps();
        });

        Schema::create('tenant_brand_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('logo_url')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->string('sidebar_color', 7)->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_brand_versions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('logo_url')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->string('sidebar_color', 7)->nullable();
            $table->unsignedTinyInteger('health_score')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('description')->nullable();
            $table->string('tenant_id')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
    }

    private function dropSchema(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('tenant_brand_versions');
        Schema::dropIfExists('tenant_brand_profiles');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }

    private function seedFixtures(): void
    {
        $this->tenant = Tenant::create([
            'id'     => self::TENANT_ID,
            'name'   => 'Test Brand Tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@brand-test.com',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        TenantMembership::create([
            'tenant_id'      => self::TENANT_ID,
            'tenant_user_id' => $this->ownerUser->id,
            'role'           => 'owner',
            'status'         => 'active',
        ]);

        $this->managerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'manager@brand-test.com',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        TenantMembership::create([
            'tenant_id'      => self::TENANT_ID,
            'tenant_user_id' => $this->managerUser->id,
            'role'           => 'manager',
            'status'         => 'active',
        ]);
    }
}
