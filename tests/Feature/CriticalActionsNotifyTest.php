<?php

namespace Tests\Feature;

use App\Events\DealExpired;
use App\Events\ImportFailed;
use App\Events\DealStageMoved;
use App\Listeners\HandleImportFailed;
use App\Listeners\HandleDealExpired;
use App\Listeners\HandleDealStageMoved;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use App\Models\Reseller;
use App\Services\NotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * NOTIFY sweep — Critical Actions module
 *
 * Covers:
 *  - ImportFailed event dispatches in-app to Admins + Managers
 *  - ImportFailed email only on hard failure (not warnings)
 *  - DealExpired dispatches to Admins and Referrer
 *  - DealStageMoved dispatches in-app to both Referrer and Admins
 *  - Wrong role does NOT receive notification
 *  - Cross-tenant isolation: Tenant B's events don't notify Tenant A's admins
 *  - Deduplication keys prevent duplicate notifications
 *  - Cache bust clears CA badge on import failure
 */
class CriticalActionsNotifyTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────

    private function createTenant(string $id = null): Tenant
    {
        return Tenant::create([
            'id'     => $id ?? (string) Str::uuid(),
            'name'   => 'Test ' . Str::random(4),
            'slug'   => 'test-' . Str::random(6),
            'status' => 'active',
        ]);
    }

    private function createAdmin(Tenant $t, string $role = 'admin'): TenantUser
    {
        $user = TenantUser::create([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Admin',
            'last_name'  => Str::random(4),
            'email'      => 'a' . Str::random(6) . '@example.com',
            'password'   => bcrypt('password'),
            'status'     => 'active',
        ]);
        TenantMembership::create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => $t->id,
            'tenant_user_id' => $user->id,
            'role'           => $role,
            'status'         => 'active',
        ]);
        return $user;
    }

    private function createReseller(Tenant $t): Reseller
    {
        return Reseller::create([
            'id'                => (string) Str::uuid(),
            'tenant_id'         => $t->id,
            'name'              => 'Ref ' . Str::random(4),
            'email'             => 'r' . Str::random(6) . '@example.com',
            'status'            => 'active',
            'assigned_leads'    => 0,
            'closed_value'      => 0,
            'performance_score' => 0,
            'is_anonymous'      => false,
        ]);
    }

    private function dispatchNotificationsFor(object $event, string $listenerClass): void
    {
        app($listenerClass)->handle($event);
    }

    // ── Phase 1: ImportFailed — in-app recipients ─────────────────

    /** @test */
    public function import_failed_notifies_all_tenant_admins_in_app(): void
    {
        $tenant = $this->createTenant();
        $admin1 = $this->createAdmin($tenant, 'admin');
        $admin2 = $this->createAdmin($tenant, 'owner');

        $event = new ImportFailed(
            batchId:    (string) Str::uuid(),
            tenantId:   $tenant->id,
            fileName:   'deals.csv',
            importType: 'deals',
            status:     'failed',
            failedRows: 10,
            totalRows:  10,
            actorId:    $admin1->id,
            actorRole:  'tenant_admin',
        );

        $this->dispatchNotificationsFor($event, HandleImportFailed::class);

        // Both admins should receive an in-app notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'tenant_admin',
            'notifiable_id'   => $admin1->id,
            'tenant_id'       => $tenant->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'tenant_admin',
            'notifiable_id'   => $admin2->id,
            'tenant_id'       => $tenant->id,
        ]);
    }

    /** @test */
    public function import_failed_notifies_managers_in_app(): void
    {
        $tenant  = $this->createTenant();
        $manager = $this->createAdmin($tenant, 'manager');

        $event = new ImportFailed(
            batchId:    (string) Str::uuid(),
            tenantId:   $tenant->id,
            fileName:   'contacts.csv',
            importType: 'contacts',
            status:     'failed',
            failedRows: 5,
            totalRows:  5,
        );

        $this->dispatchNotificationsFor($event, HandleImportFailed::class);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'tenant_admin',
            'notifiable_id'   => $manager->id,
            'tenant_id'       => $tenant->id,
        ]);
    }

    /** @test */
    public function import_failed_sends_email_on_hard_failure(): void
    {
        Mail::fake();

        $tenant = $this->createTenant();
        $admin  = $this->createAdmin($tenant, 'admin');

        $event = new ImportFailed(
            batchId:    (string) Str::uuid(),
            tenantId:   $tenant->id,
            fileName:   'import.csv',
            importType: 'deals',
            status:     'failed',
            failedRows: 3,
            totalRows:  3,
        );

        $this->dispatchNotificationsFor($event, HandleImportFailed::class);

        Mail::assertQueued(\App\Mail\ImportFailedMail::class);
    }

    /** @test */
    public function import_warnings_does_not_send_email(): void
    {
        Mail::fake();

        $tenant = $this->createTenant();
        $admin  = $this->createAdmin($tenant, 'admin');

        $event = new ImportFailed(
            batchId:    (string) Str::uuid(),
            tenantId:   $tenant->id,
            fileName:   'import.csv',
            importType: 'deals',
            status:     'completed_with_warnings',
            failedRows: 2,
            totalRows:  10,
        );

        $this->dispatchNotificationsFor($event, HandleImportFailed::class);

        Mail::assertNothingQueued();
    }

    // ── Phase 2: ImportFailed — cross-tenant isolation ─────────────

    /** @test */
    public function import_failed_does_not_notify_other_tenant_admins(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $adminA  = $this->createAdmin($tenantA, 'admin');
        $adminB  = $this->createAdmin($tenantB, 'admin');

        $event = new ImportFailed(
            batchId:    (string) Str::uuid(),
            tenantId:   $tenantB->id,  // Tenant B's import
            fileName:   'b.csv',
            importType: 'deals',
            status:     'failed',
            failedRows: 1,
            totalRows:  1,
        );

        $this->dispatchNotificationsFor($event, HandleImportFailed::class);

        // Tenant A's admin must NOT receive a notification
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => 'tenant_admin',
            'notifiable_id'   => $adminA->id,
            'tenant_id'       => $tenantB->id,
        ]);

        // Tenant B's admin should receive it
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'tenant_admin',
            'notifiable_id'   => $adminB->id,
            'tenant_id'       => $tenantB->id,
        ]);
    }

    // ── Phase 3: ImportFailed — dedup ─────────────────────────────

    /** @test */
    public function import_failed_deduplication_prevents_double_notification(): void
    {
        $tenant  = $this->createTenant();
        $admin   = $this->createAdmin($tenant, 'admin');
        $batchId = (string) Str::uuid();

        $event = new ImportFailed(
            batchId:    $batchId,
            tenantId:   $tenant->id,
            fileName:   'import.csv',
            importType: 'deals',
            status:     'failed',
            failedRows: 1,
            totalRows:  1,
        );

        // Fire twice (simulating listener retry)
        $this->dispatchNotificationsFor($event, HandleImportFailed::class);
        $this->dispatchNotificationsFor($event, HandleImportFailed::class);

        $count = Notification::where('notifiable_type', 'tenant_admin')
            ->where('notifiable_id', $admin->id)
            ->where('tenant_id', $tenant->id)
            ->count();

        $this->assertEquals(1, $count, 'Deduplication should prevent duplicate notifications');
    }

    // ── Phase 4: ImportFailed — cache bust ────────────────────────

    /** @test */
    public function import_failed_busts_admin_ca_badge_cache(): void
    {
        $tenant = $this->createTenant();
        $admin  = $this->createAdmin($tenant, 'admin');

        // Plant a fake badge in cache
        Cache::put("ca_badge_{$tenant->id}_{$admin->id}", 5, 300);
        Cache::put("ca_badge_suppressed:{$tenant->id}:{$admin->id}", 1, 300);

        $event = new ImportFailed(
            batchId:    (string) Str::uuid(),
            tenantId:   $tenant->id,
            fileName:   'import.csv',
            importType: 'deals',
            status:     'failed',
            failedRows: 1,
            totalRows:  1,
        );

        $this->dispatchNotificationsFor($event, HandleImportFailed::class);

        $this->assertFalse(Cache::has("ca_badge_{$tenant->id}_{$admin->id}"),
            'CA badge cache should be cleared after import failure');
        $this->assertFalse(Cache::has("ca_badge_suppressed:{$tenant->id}:{$admin->id}"),
            'CA badge suppressor should be cleared after import failure');
    }

    // ── Phase 5: DealExpired — recipients ─────────────────────────

    /** @test */
    public function deal_expired_notifies_tenant_admins_in_app(): void
    {
        $tenant   = $this->createTenant();
        $admin    = $this->createAdmin($tenant, 'admin');
        $reseller = $this->createReseller($tenant);

        $event = new DealExpired(
            leadId:        (string) Str::uuid(),
            leadName:      'Test Deal',
            tenantId:      $tenant->id,
            resellerName:  $reseller->name,
            stage:         'introduction',
            resellerEmail: $reseller->email,
        );

        $this->dispatchNotificationsFor($event, HandleDealExpired::class);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'tenant_admin',
            'notifiable_id'   => $admin->id,
            'tenant_id'       => $tenant->id,
            'category'        => 'deal_pipeline',
        ]);
    }

    /** @test */
    public function deal_expired_notifies_referrer_in_app(): void
    {
        $tenant   = $this->createTenant();
        $admin    = $this->createAdmin($tenant, 'admin');
        $reseller = $this->createReseller($tenant);
        $leadId   = (string) Str::uuid();

        $event = new DealExpired(
            leadId:        $leadId,
            leadName:      'Test Deal',
            tenantId:      $tenant->id,
            resellerName:  $reseller->name,
            stage:         'introduction',
            resellerEmail: $reseller->email,
        );

        $this->dispatchNotificationsFor($event, HandleDealExpired::class);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'reseller',
            'notifiable_id'   => $reseller->id,
            'tenant_id'       => $tenant->id,
        ]);
    }

    /** @test */
    public function deal_expired_does_not_notify_other_tenant_admins(): void
    {
        $tenantA  = $this->createTenant();
        $tenantB  = $this->createTenant();
        $adminA   = $this->createAdmin($tenantA, 'admin');
        $reseller = $this->createReseller($tenantB);

        $event = new DealExpired(
            leadId:        (string) Str::uuid(),
            leadName:      'Deal B',
            tenantId:      $tenantB->id,
            resellerName:  $reseller->name,
            stage:         'introduction',
        );

        $this->dispatchNotificationsFor($event, HandleDealExpired::class);

        // Tenant A's admin must NOT receive anything
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => 'tenant_admin',
            'notifiable_id'   => $adminA->id,
        ]);
    }

    // ── Phase 6: DealStageMoved — in-app to both Referrer and Admin ─

    /** @test */
    public function deal_stage_moved_notifies_admin_in_app(): void
    {
        $tenant   = $this->createTenant();
        $admin    = $this->createAdmin($tenant, 'admin');
        $reseller = $this->createReseller($tenant);
        $leadId   = (string) Str::uuid();

        $event = new DealStageMoved(
            leadId:        $leadId,
            leadName:      'Stage Deal',
            tenantId:      $tenant->id,
            resellerName:  $reseller->name,
            fromStage:     'introduction',
            toStage:       'closing',
            dealValue:     50000,
            movedByName:   'Admin User',
            resellerEmail: $reseller->email,
            resellerId:    (string) $reseller->id,
        );

        $this->dispatchNotificationsFor($event, HandleDealStageMoved::class);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'tenant_admin',
            'notifiable_id'   => $admin->id,
            'tenant_id'       => $tenant->id,
            'category'        => 'deal_pipeline',
        ]);
    }

    /** @test */
    public function deal_stage_moved_notifies_referrer_in_app(): void
    {
        $tenant   = $this->createTenant();
        $admin    = $this->createAdmin($tenant, 'admin');
        $reseller = $this->createReseller($tenant);
        $leadId   = (string) Str::uuid();

        $event = new DealStageMoved(
            leadId:        $leadId,
            leadName:      'Stage Deal',
            tenantId:      $tenant->id,
            resellerName:  $reseller->name,
            fromStage:     'introduction',
            toStage:       'closing',
            dealValue:     50000,
            movedByName:   'Admin User',
            resellerEmail: $reseller->email,
            resellerId:    (string) $reseller->id,
        );

        $this->dispatchNotificationsFor($event, HandleDealStageMoved::class);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'reseller',
            'notifiable_id'   => $reseller->id,
            'tenant_id'       => $tenant->id,
        ]);
    }

    /** @test */
    public function deal_stage_moved_does_not_notify_wrong_tenant_admin(): void
    {
        $tenantA  = $this->createTenant();
        $tenantB  = $this->createTenant();
        $adminA   = $this->createAdmin($tenantA, 'admin');
        $reseller = $this->createReseller($tenantB);

        $event = new DealStageMoved(
            leadId:        (string) Str::uuid(),
            leadName:      'Deal B',
            tenantId:      $tenantB->id,
            resellerName:  $reseller->name,
            fromStage:     'introduction',
            toStage:       'closing',
            dealValue:     10000,
            movedByName:   'Admin',
            resellerEmail: $reseller->email,
            resellerId:    (string) $reseller->id,
        );

        $this->dispatchNotificationsFor($event, HandleDealStageMoved::class);

        // Tenant A admin must NOT be notified of Tenant B's deal
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => 'tenant_admin',
            'notifiable_id'   => $adminA->id,
        ]);
    }

    // ── Phase 7: Members must NOT receive Critical Action notifications ──

    /** @test */
    public function member_role_does_not_receive_import_failed_notification(): void
    {
        $tenant = $this->createTenant();
        $member = $this->createAdmin($tenant, 'member');

        // NotificationDispatchService only targets owner/admin/manager roles
        $event = new ImportFailed(
            batchId:    (string) Str::uuid(),
            tenantId:   $tenant->id,
            fileName:   'import.csv',
            importType: 'deals',
            status:     'failed',
            failedRows: 1,
            totalRows:  1,
        );

        $this->dispatchNotificationsFor($event, HandleImportFailed::class);

        // The member (role='member') should NOT receive a notification
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => 'tenant_admin',
            'notifiable_id'   => $member->id,
        ]);
    }

    // ── Phase 8: DealExpired — CA badge is busted ─────────────────

    /** @test */
    public function deal_expired_busts_admin_ca_badge_cache(): void
    {
        $tenant   = $this->createTenant();
        $admin    = $this->createAdmin($tenant, 'admin');
        $reseller = $this->createReseller($tenant);

        Cache::put("ca_badge_{$tenant->id}_{$admin->id}", 3, 300);

        $event = new DealExpired(
            leadId:       (string) Str::uuid(),
            leadName:     'Test',
            tenantId:     $tenant->id,
            resellerName: $reseller->name,
            stage:        'introduction',
        );

        $this->dispatchNotificationsFor($event, HandleDealExpired::class);

        $this->assertFalse(
            Cache::has("ca_badge_{$tenant->id}_{$admin->id}"),
            'Deal expiry should clear the admin CA badge'
        );
    }
}
