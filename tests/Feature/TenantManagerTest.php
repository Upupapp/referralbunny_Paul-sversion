<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantManagerTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'id'     => (string) Str::uuid(),
            'name'   => 'Test Tenant ' . Str::random(4),
            'slug'   => 'test-' . Str::random(6),
            'status' => 'active',
        ]);
    }

    private function createUser(array $attrs = []): TenantUser
    {
        return TenantUser::create(array_merge([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'user-' . Str::random(6) . '@example.com',
            'password'   => bcrypt('password'),
            'status'     => 'active',
        ], $attrs));
    }

    private function createMembership(Tenant $tenant, TenantUser $user, string $role, array $extra = []): TenantMembership
    {
        return TenantMembership::create(array_merge([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => $tenant->id,
            'tenant_user_id' => $user->id,
            'role'           => $role,
            'status'         => 'active',
        ], $extra));
    }

    // ── Tests ─────────────────────────────────────────────────────

    /**
     * Test 1: A tenant admin can invite a user with the manager role.
     */
    public function test_tenant_admin_can_invite_manager(): void
    {
        $tenant = $this->createTenant();
        $admin  = $this->createUser();
        $this->createMembership($tenant, $admin, 'admin');

        $response = $this->actingAs($admin, 'tenant')
            ->post("/tenant/{$tenant->id}/users/invite", [
                'email' => 'newmanager@example.com',
                'role'  => 'manager',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $tenant->id,
            'email'     => 'newmanager@example.com',
            'role'      => 'manager',
            'status'    => 'pending',
        ]);
    }

    /**
     * Test 2: Manager cannot delete the tenant account — permission locked to false.
     */
    public function test_manager_cannot_delete_tenant_account(): void
    {
        $tenant  = $this->createTenant();
        $manager = $this->createUser();
        $membership = $this->createMembership($tenant, $manager, 'manager');

        $permService = app(PermissionService::class);
        $canDelete   = $permService->can($membership, 'delete_tenant_account');

        $this->assertFalse($canDelete, 'Manager must not be able to delete tenant account.');
    }

    /**
     * Test 3: Manager cannot transfer ownership — permission locked to false.
     */
    public function test_manager_cannot_transfer_ownership(): void
    {
        $tenant  = $this->createTenant();
        $manager = $this->createUser();
        $membership = $this->createMembership($tenant, $manager, 'manager');

        $permService  = app(PermissionService::class);
        $canTransfer  = $permService->can($membership, 'transfer_tenant_ownership');

        $this->assertFalse($canTransfer, 'Manager must not be able to transfer ownership.');
    }

    /**
     * Test 4: Manager billing access is disabled by default.
     */
    public function test_manager_billing_disabled_by_default(): void
    {
        $tenant  = $this->createTenant();
        $manager = $this->createUser();
        $membership = $this->createMembership($tenant, $manager, 'manager');

        $this->assertFalse((bool) $membership->can_manage_billing, 'can_manage_billing must default to false.');

        $permService = app(PermissionService::class);
        $canBilling  = $permService->can($membership, 'manage_billing_and_subscription');
        $this->assertFalse($canBilling, 'Billing permission must be false by default for managers.');
    }

    /**
     * Test 5: An admin can enable billing access for a manager.
     */
    public function test_admin_can_enable_billing_for_manager(): void
    {
        $tenant  = $this->createTenant();
        $admin   = $this->createUser();
        $manager = $this->createUser();
        $this->createMembership($tenant, $admin, 'admin');
        $membership = $this->createMembership($tenant, $manager, 'manager');

        $response = $this->actingAs($admin, 'tenant')
            ->post("/tenant/{$tenant->id}/users/{$manager->id}/billing-toggle", [
                'enable' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $membership->refresh();
        $this->assertTrue((bool) $membership->can_manage_billing, 'Billing should now be enabled for manager.');
    }

    /**
     * Test 6: Manager with billing enabled can view billing permissions.
     */
    public function test_manager_with_billing_enabled_can_view_billing(): void
    {
        $tenant  = $this->createTenant();
        $manager = $this->createUser();
        $membership = $this->createMembership($tenant, $manager, 'manager', [
            'can_manage_billing' => true,
        ]);

        $permService = app(PermissionService::class);

        $this->assertTrue($permService->can($membership, 'manage_billing_and_subscription'));
        $this->assertTrue($permService->can($membership, 'view_invoices'));
        $this->assertTrue($permService->can($membership, 'upgrade_subscription'));
        $this->assertTrue($permService->can($membership, 'change_payment_method'));
    }

    /**
     * Test 7: Manager cannot grant themselves higher/locked permissions.
     */
    public function test_manager_cannot_grant_self_higher_permissions(): void
    {
        $tenant  = $this->createTenant();
        $manager = $this->createUser();
        $membership = $this->createMembership($tenant, $manager, 'manager');

        $permService = app(PermissionService::class);

        // Attempt to update locked permissions via the service directly
        $permService->updateManagerPermissions($membership, [
            'delete_tenant_account'    => true,
            'transfer_tenant_ownership'=> true,
            'cancel_subscription'      => true,
        ]);

        $membership->refresh();

        // Locked permissions must remain false regardless of input
        foreach (PermissionService::MANAGER_LOCKED_FALSE as $lockedPerm) {
            $this->assertFalse(
                $permService->can($membership, $lockedPerm),
                "Locked permission '{$lockedPerm}' must remain false."
            );
        }

        // Also verify via permissions_json if custom
        if (is_array($membership->permissions_json)) {
            foreach (PermissionService::MANAGER_LOCKED_FALSE as $lockedPerm) {
                $this->assertFalse(
                    (bool) ($membership->permissions_json[$lockedPerm] ?? false),
                    "permissions_json['{$lockedPerm}'] must be false."
                );
            }
        }
    }

    /**
     * Test 8: Manager cannot invite other managers or admins — role hierarchy blocked.
     */
    public function test_manager_cannot_invite_admin(): void
    {
        $tenant  = $this->createTenant();
        $manager = $this->createUser();
        $this->createMembership($tenant, $manager, 'manager');

        // Attempt to invite an admin
        $response = $this->actingAs($manager, 'tenant')
            ->post("/tenant/{$tenant->id}/users/invite", [
                'email' => 'newadmin@example.com',
                'role'  => 'admin',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['role']);

        $this->assertDatabaseMissing('tenant_invitations', [
            'email' => 'newadmin@example.com',
            'role'  => 'admin',
        ]);
    }

    /**
     * Test 9: PermissionService enforces all MANAGER_LOCKED_FALSE permissions as false.
     */
    public function test_permission_service_locked_permissions(): void
    {
        $tenant  = $this->createTenant();
        $manager = $this->createUser();
        $membership = $this->createMembership($tenant, $manager, 'manager', [
            'is_custom_permissions' => true,
            'permissions_json'      => array_fill_keys(PermissionService::MANAGER_LOCKED_FALSE, true),
        ]);

        $permService = app(PermissionService::class);

        foreach (PermissionService::MANAGER_LOCKED_FALSE as $perm) {
            $this->assertFalse(
                $permService->can($membership, $perm),
                "MANAGER_LOCKED_FALSE permission '{$perm}' must always return false, even if set in permissions_json."
            );

            $this->assertTrue(
                $permService->isLocked('manager', $perm),
                "isLocked() must return true for '{$perm}'."
            );
        }
    }

    /**
     * Test 10: Accepting an invitation creates an active membership for the new user.
     */
    public function test_invitation_acceptance_creates_membership(): void
    {
        $tenant = $this->createTenant();
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');

        // Create a pending invitation
        $invitation = TenantInvitation::create([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => $tenant->id,
            'email'      => 'invitee@example.com',
            'role'       => 'manager',
            'status'     => 'pending',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->post("/tenant/accept-invite/{$invitation->token}", [
            'first_name'            => 'New',
            'last_name'             => 'Manager',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();

        // Invitation status should be accepted
        $this->assertDatabaseHas('tenant_invitations', [
            'id'     => $invitation->id,
            'status' => 'accepted',
        ]);

        // A new TenantUser should exist
        $this->assertDatabaseHas('tenant_users', [
            'email' => 'invitee@example.com',
        ]);

        // An active membership should have been created
        $newUser = TenantUser::where('email', 'invitee@example.com')->first();
        $this->assertNotNull($newUser, 'TenantUser should have been created.');

        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id'      => $tenant->id,
            'tenant_user_id' => $newUser->id,
            'role'           => 'manager',
            'status'         => 'active',
        ]);
    }
}
