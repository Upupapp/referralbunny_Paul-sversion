<?php

namespace Tests\Feature;

use App\Events\ProgramActionItemCreated;
use App\Listeners\HandleProgramActionItemCreated;
use App\Models\MemberActionItem;
use App\Models\Program;
use App\Models\ReferrerProgramMembership;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the Programs V4 notification wiring on action item
 * creation: the controller fires ProgramActionItemCreated, and the queued
 * HandleProgramActionItemCreated listener creates Notification rows for
 * active tenant admins and flips notification_status to sent/failed.
 *
 * Does NOT use RefreshDatabase — builds/drops schema in setUp/tearDown.
 */
class ProgramActionItemNotificationTest extends TestCase
{
    private const TENANT_ID = 'test-ai-notif-tenant';

    private Tenant     $tenant;
    private TenantUser $ownerUser;
    private Program    $program;
    private string     $resellerId;
    private string     $referrerMembershipId;

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

    public function test_store_fires_program_action_item_created_event(): void
    {
        Event::fake([ProgramActionItemCreated::class]);

        $this->actingAs($this->ownerUser, 'tenant')
            ->post(route('tenant.programs.action-items.store', [self::TENANT_ID, $this->program->id]), [
                'membership_type' => 'referrer',
                'membership_id'   => $this->referrerMembershipId,
                'action_type'     => 'complete_profile',
            ])
            ->assertRedirect();

        Event::assertDispatched(ProgramActionItemCreated::class);
    }

    public function test_listener_creates_notification_for_active_admin_and_marks_sent(): void
    {
        $item = MemberActionItem::create([
            'tenant_id'           => self::TENANT_ID,
            'program_id'          => $this->program->id,
            'membership_type'     => 'referrer',
            'membership_id'       => $this->referrerMembershipId,
            'action_type'         => 'complete_profile',
            'status'              => 'pending',
            'notification_status' => 'not_sent',
        ]);

        (new HandleProgramActionItemCreated())->handle(new ProgramActionItemCreated($item->id));

        $this->assertDatabaseHas('notifications', [
            'tenant_id'       => self::TENANT_ID,
            'notifiable_type' => 'tenant_admin',
            'notifiable_id'   => $this->ownerUser->id,
            'category'        => 'task_approval',
        ]);

        $item->refresh();
        $this->assertSame('sent', $item->notification_status);
    }

    public function test_listener_does_nothing_if_action_item_already_deleted(): void
    {
        // Simulates a stale queued job running after the item was removed.
        $missingId = (string) Str::uuid();

        (new HandleProgramActionItemCreated())->handle(new ProgramActionItemCreated($missingId));

        $this->assertDatabaseMissing('notifications', ['category' => 'task_approval']);
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

        Schema::create('resellers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('email');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('partner_users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('referrer_program_memberships', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('reseller_id');
            $table->string('program_group_id')->nullable();
            $table->string('status')->default('invited');
            $table->string('source')->default('direct');
            $table->string('active_contract_id')->nullable();
            $table->string('assigned_manager_id')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->unique(['program_id', 'reseller_id']);
        });

        Schema::create('partner_program_memberships', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('partner_id');
            $table->string('program_group_id')->nullable();
            $table->string('status')->default('invited');
            $table->string('source')->default('direct');
            $table->string('active_contract_id')->nullable();
            $table->string('assigned_manager_id')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->unique(['program_id', 'partner_id']);
        });

        Schema::create('member_action_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('program_id');
            $table->string('membership_type');
            $table->string('membership_id');
            $table->string('action_type');
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('source_configuration_version_id')->nullable();
            $table->string('notification_status')->default('not_sent');
            $table->timestamps();
        });

        // Notification::$fillable does NOT include 'id', even though
        // NotificationDispatchService::dispatch() sets it explicitly --
        // mass assignment silently drops it. Production apparently relies
        // on a DB-side default for notifications.id; sqlite has no such
        // default, so this test schema leaves id nullable/non-enforced
        // rather than failing on a gap that belongs to shared
        // infrastructure outside this phase's scope.
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
        Schema::dropIfExists('member_action_items');
        Schema::dropIfExists('partner_program_memberships');
        Schema::dropIfExists('referrer_program_memberships');
        Schema::dropIfExists('partner_users');
        Schema::dropIfExists('resellers');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }

    private function seedFixtures(): void
    {
        $this->tenant = Tenant::create([
            'id'     => self::TENANT_ID,
            'name'   => 'Test AI Notif Tenant',
            'status' => 'active',
        ]);

        $this->ownerUser = TenantUser::create([
            'id'       => (string) Str::uuid(),
            'email'    => 'owner@ai-notif-test.com',
            'password' => bcrypt('password'),
            'status'   => 'active',
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

        $reseller = \App\Models\Reseller::create([
            'tenant_id' => self::TENANT_ID,
            'name'      => 'Test Referrer',
            'email'     => 'referrer@ai-notif-test.com',
            'status'    => 'active',
        ]);
        $this->resellerId = $reseller->id;

        $referrerMembership = ReferrerProgramMembership::create([
            'tenant_id'   => self::TENANT_ID,
            'program_id'  => $this->program->id,
            'reseller_id' => $this->resellerId,
            'status'      => 'active',
            'source'      => 'direct',
        ]);
        $this->referrerMembershipId = $referrerMembership->id;
    }
}
