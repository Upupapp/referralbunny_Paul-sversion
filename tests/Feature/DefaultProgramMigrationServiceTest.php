<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Tenant;
use App\Services\Programs\DefaultProgramMigrationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Feature tests for DefaultProgramMigrationService::ensureDefault().
 *
 * This service has no caller yet (dormant scaffolding for a future V3->V4
 * migration trigger), but its protected-tenant guard is tested now so the
 * decision -- lgu-ids stays fully blocked, no scoped exception -- is locked
 * in before any caller is ever wired up.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class DefaultProgramMigrationServiceTest extends TestCase
{
    private const TENANT_ID        = 'test-default-program-tenant';
    private const PROTECTED_TENANT = 'lgu-ids';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    public function test_creates_default_program_for_normal_tenant(): void
    {
        $tenant = Tenant::create(['id' => self::TENANT_ID, 'name' => 'Test Tenant', 'status' => 'active']);

        $program = (new DefaultProgramMigrationService())->ensureDefault($tenant);

        $this->assertTrue($program->is_default);
        $this->assertSame(self::TENANT_ID, $program->tenant_id);
    }

    public function test_is_idempotent(): void
    {
        $tenant = Tenant::create(['id' => self::TENANT_ID, 'name' => 'Test Tenant', 'status' => 'active']);

        $first  = (new DefaultProgramMigrationService())->ensureDefault($tenant);
        $second = (new DefaultProgramMigrationService())->ensureDefault($tenant);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Program::where('tenant_id', self::TENANT_ID)->count());
    }

    public function test_rejects_protected_tenant(): void
    {
        $tenant = Tenant::create(['id' => self::PROTECTED_TENANT, 'name' => 'LGU IDS', 'status' => 'active']);

        $this->expectException(NotFoundHttpException::class);

        (new DefaultProgramMigrationService())->ensureDefault($tenant);

        $this->assertSame(0, Program::where('tenant_id', self::PROTECTED_TENANT)->count());
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    private function buildSchema(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('program_name')->nullable();
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
            $table->boolean('is_default')->default(false);
            $table->timestamp('launched_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_referral_program_drafts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_name')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('program_id')->nullable();
            $table->timestamps();
        });
    }

    private function dropSchema(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('tenant_referral_program_drafts');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('tenants');
    }
}
