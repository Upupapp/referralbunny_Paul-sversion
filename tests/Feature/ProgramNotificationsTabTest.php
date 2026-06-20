<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the Programs V4 "Notifications" tab — a read-only,
 * program-scoped feed of in-app notifications already dispatched via the
 * existing Notification/NotificationDispatchService infrastructure.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramNotificationsTabTest extends TestCase
{
    private const TENANT_ID = 'test-notif-tab-tenant';

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

    public function test_get_workspace_notifications_tab_renders_program_scoped_notification(): void
    {
        $this->makeNotification($this->program->id, 'Test notification for this program');

        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=notifications')
            ->assertStatus(200)
            ->assertSee('Test notification for this program');
    }

    public function test_notification_from_another_program_is_excluded(): void
    {
        $otherProgram = Program::create([
            'tenant_id'    => self::TENANT_ID,
            'name'         => 'Other Program',
            'program_type' => 'referral',
            'status'       => 'active',
        ]);
        $this->makeNotification($otherProgram->id, 'Other program notification');

        $response = $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=notifications');

        $response->assertStatus(200);
        $response->assertDontSee('Other program notification');
    }

    public function test_get_workspace_other_tabs_render_without_error_when_notifications_data_is_null(): void
    {
        $this->actingAs($this->ownerUser, 'tenant')
            ->get(route('tenant.programs.workspace', [self::TENANT_ID, $this->program->id]) . '?tab=overview')
            ->assertStatus(200);
    }

    // ── Schema / Fixtures ─────────────────────────────────────────────────────

    private function makeNotification(string $programId, string $title): void
    {
        DB::table('notifications')->insert([
            'id'              => (string) Str::uuid(),
            'tenant_id'       => self::TENANT_ID,
            'notifiable_type' => 'tenant_admin',
            'notifiable_id'   => $this->ownerUser->id,
            'category'        => 'task_approval',
            'priority'        => 'normal',
            'title'           => $title,
            'message'         => 'Body text',
            'metadata_json'   => json_encode(['program_id' => $programId]),
            'is_read'         => false,
            'is_dismissed'    => false,
            'sent_at'         => now(),
            'created_at'      => now(),
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

        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id')->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('notifiable_type')->nullable();
            $table->string('notifiable_id')->nullable();
            $table->string('category')->nullable();
            $table->string('type')->nullable();
            $table->string('priority')->default('normal');
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('action_url')->nullable();
            $table->string('action_label')->nullable();
            $table->string('channel')->nullable();
            $table->string('frequency_type')->nullable();
            $table->integer('escalation_level')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_dismissed')->default(false);
            $table->string('deduplication_key')->nullable();
            $table->text('metadata_json')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();
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
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }

    private function seedFixtures(): void
    {
        $this->tenant = Tenant::create([
            'id'     => self::TENANT_ID,
            'name'   => 'Test Notif Tab Tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'         => (string) Str::uuid(),
            'email'      => 'owner@notif-tab-test.com',
            'password'   => bcrypt('password'),
            'status'     => 'active',
            'first_name' => 'Test',
            'last_name'  => 'Owner',
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
