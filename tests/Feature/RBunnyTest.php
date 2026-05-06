<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RBunnyTest extends TestCase
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

    private function createTenantUser(array $attrs = []): TenantUser
    {
        return TenantUser::create(array_merge([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'u' . Str::random(6) . '@example.com',
            'password'   => bcrypt('password'),
            'status'     => 'active',
        ], $attrs));
    }

    private function createMembership(Tenant $tenant, TenantUser $user, string $role = 'admin'): TenantMembership
    {
        return TenantMembership::create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => $tenant->id,
            'tenant_user_id' => $user->id,
            'role'           => $role,
            'status'         => 'active',
        ]);
    }

    private function createReseller(Tenant $tenant, array $attrs = []): Reseller
    {
        return Reseller::create(array_merge([
            'id'                => (string) Str::uuid(),
            'tenant_id'         => $tenant->id,
            'name'              => 'Ref ' . Str::random(4),
            'email'             => 'ref' . Str::random(6) . '@example.com',
            'status'            => 'active',
            'assigned_leads'    => 0,
            'closed_value'      => 0,
            'performance_score' => 0,
            'is_anonymous'      => false,
        ], $attrs));
    }

    private function createPartner(Tenant $tenant): Partner
    {
        return Partner::create([
            'id'                 => (string) Str::uuid(),
            'tenant_id'          => $tenant->id,
            'email'              => 'p' . Str::random(6) . '@example.com',
            'first_name'         => 'Partner',
            'last_name'          => 'User',
            'status'             => 'active',
            'setup_completed_at' => now(),
        ]);
    }

    // ── 1. Status endpoint responds per guard ─────────────────────

    /** @test */
    public function admin_gets_r_bunny_status()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->getJson("/api/r-bunny/status?tenant_id={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('role_key', 'admin');
    }

    /** @test */
    public function manager_gets_r_bunny_status()
    {
        $tenant  = $this->createTenant();
        $manager = $this->createTenantUser();
        $this->createMembership($tenant, $manager, 'manager');

        $this->actingAs($manager, 'tenant')
            ->getJson("/api/r-bunny/status?tenant_id={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('role_key', 'manager');
    }

    /** @test */
    public function referrer_gets_r_bunny_status()
    {
        $tenant   = $this->createTenant();
        $reseller = $this->createReseller($tenant);

        $this->actingAs($reseller, 'reseller')
            ->getJson("/api/r-bunny/status")
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('role_key', 'referrer');
    }

    /** @test */
    public function partner_gets_r_bunny_status()
    {
        $tenant  = $this->createTenant();
        $partner = $this->createPartner($tenant);

        $this->actingAs($partner, 'partner')
            ->getJson("/api/r-bunny/status")
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('role_key', 'partner');
    }

    /** @test */
    public function unauthenticated_cannot_access_r_bunny()
    {
        $this->getJson("/api/r-bunny/status")
            ->assertUnauthorized();
    }

    // ── 2. Quick actions are role-filtered ───────────────────────

    /** @test */
    public function admin_sees_admin_quick_actions()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $res  = $this->actingAs($admin, 'tenant')
            ->getJson("/api/r-bunny/status?tenant_id={$tenant->id}");
        $keys = collect($res->json('quick_actions'))->pluck('key')->toArray();

        $this->assertContains('open_deals', $keys);
        $this->assertContains('open_messages', $keys);
    }

    /** @test */
    public function partner_does_not_see_tenant_admin_quick_actions()
    {
        $tenant  = $this->createTenant();
        $partner = $this->createPartner($tenant);

        $res  = $this->actingAs($partner, 'partner')
            ->getJson("/api/r-bunny/status");
        $keys = collect($res->json('quick_actions'))->pluck('key')->toArray();

        $this->assertNotContains('open_deals', $keys);      // tenant admin action
        $this->assertNotContains('open_referrers', $keys);  // tenant admin action
        $this->assertContains('pt_deals', $keys);           // partner action
    }

    /** @test */
    public function referrer_does_not_see_tenant_admin_actions()
    {
        $tenant   = $this->createTenant();
        $reseller = $this->createReseller($tenant);

        $res  = $this->actingAs($reseller, 'reseller')
            ->getJson("/api/r-bunny/status");
        $keys = collect($res->json('quick_actions'))->pluck('key')->toArray();

        $this->assertNotContains('open_users', $keys);
        $this->assertNotContains('open_imports', $keys);
        $this->assertContains('rs_deals', $keys);
    }

    // ── 3. Page help resolves by URL ─────────────────────────────

    /** @test */
    public function page_help_resolves_for_deals_page()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $res = $this->actingAs($admin, 'tenant')
            ->getJson("/api/r-bunny/status?tenant_id={$tenant->id}&page=/tenant/{$tenant->id}/deals");

        $this->assertNotNull($res->json('page_help'));
        $this->assertEquals('Deals', $res->json('page_help.title'));
    }

    /** @test */
    public function page_help_resolves_for_messages_page()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $res = $this->actingAs($admin, 'tenant')
            ->getJson("/api/r-bunny/status?tenant_id={$tenant->id}&page=/tenant/{$tenant->id}/messages");

        $this->assertEquals('Messages', $res->json('page_help.title'));
    }

    /** @test */
    public function page_help_is_null_for_unknown_page()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $res = $this->actingAs($admin, 'tenant')
            ->getJson("/api/r-bunny/status?tenant_id={$tenant->id}&page=/some/unknown/page");

        $this->assertNull($res->json('page_help'));
    }

    // ── 4. Dismiss / Snooze ───────────────────────────────────────

    /** @test */
    public function user_can_dismiss_suggestion()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->postJson("/api/r-bunny/dismiss", ['key' => 'expiring_deals'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('r_bunny_suggestions', [
            'user_id'        => (string) $admin->id,
            'suggestion_key' => 'expiring_deals',
            'status'         => 'dismissed',
        ]);
    }

    /** @test */
    public function user_can_snooze_suggestion()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->postJson("/api/r-bunny/snooze", ['key' => 'unread_messages', 'hours' => 8])
            ->assertOk();

        $this->assertDatabaseHas('r_bunny_suggestions', [
            'user_id'        => (string) $admin->id,
            'suggestion_key' => 'unread_messages',
            'status'         => 'snoozed',
        ]);
    }

    /** @test */
    public function dismissed_suggestions_do_not_reappear()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        // Dismiss a suggestion
        $this->actingAs($admin, 'tenant')
            ->postJson("/api/r-bunny/dismiss", ['key' => 'expiring_deals']);

        // Create an expiring deal that would normally trigger the suggestion
        \Illuminate\Support\Facades\DB::table('leads')->insert([
            'id'            => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'     => $tenant->id,
            'name'          => 'Expiring Deal',
            'stage'         => 'introduction',
            'status'        => 'expiring',
            'reseller_name' => 'Test',
            'days_left'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $res  = $this->actingAs($admin, 'tenant')
            ->getJson("/api/r-bunny/status?tenant_id={$tenant->id}");
        $keys = collect($res->json('suggestions'))->pluck('key')->toArray();

        $this->assertNotContains('expiring_deals', $keys);
    }

    // ── 5. Tenant isolation ───────────────────────────────────────

    /** @test */
    public function r_bunny_does_not_show_another_tenants_data()
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $adminA  = $this->createTenantUser();
        $this->createMembership($tenantA, $adminA, 'admin');

        // Create expiring deal in tenant B
        \Illuminate\Support\Facades\DB::table('leads')->insert([
            'id'            => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'     => $tenantB->id,
            'name'          => 'TenantB Deal',
            'stage'         => 'introduction',
            'status'        => 'expiring',
            'reseller_name' => 'Other',
            'days_left'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Tenant A admin should NOT see tenant B's expiring deal suggestion
        $res  = $this->actingAs($adminA, 'tenant')
            ->getJson("/api/r-bunny/status?tenant_id={$tenantA->id}");
        $suggestions = $res->json('suggestions');

        // The expiring_deals suggestion should not appear (no expiring deals in tenant A)
        $keys = collect($suggestions)->pluck('key')->toArray();
        $this->assertNotContains('expiring_deals', $keys);
    }

    /** @test */
    public function tenant_id_from_request_is_validated_against_membership()
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $adminA  = $this->createTenantUser();
        $this->createMembership($tenantA, $adminA, 'admin');

        // Admin A tries to query as if they're in Tenant B (they have no membership there)
        $res = $this->actingAs($adminA, 'tenant')
            ->getJson("/api/r-bunny/status?tenant_id={$tenantB->id}");

        // Should still work but fall back to tenant A (user's actual tenant)
        // Or return enabled=false if no valid tenant can be resolved for tenant B
        $this->assertOk($res);
        // Critical: tenant_id in response must NOT be tenantB
        $this->assertNotEquals($tenantB->id, $res->json('tenant_id'));
    }

    // ── 6. Handoff ───────────────────────────────────────────────

    /** @test */
    public function user_can_request_handoff()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->postJson("/api/r-bunny/handoff", ['issue' => 'Deal cannot be updated'])
            ->assertOk()
            ->assertJsonPath('handled', true)
            ->assertJsonStructure(['handled', 'ref', 'message']);
    }

    // ── 7. Preferences ───────────────────────────────────────────

    /** @test */
    public function user_can_get_preferences()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->getJson("/api/r-bunny/preferences")
            ->assertOk()
            ->assertJsonStructure(['show_proactive_tips', 'show_onboarding_tips', 'collapsed_by_default']);
    }

    /** @test */
    public function user_can_update_preferences()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->postJson("/api/r-bunny/preferences", ['collapsed_by_default' => true])
            ->assertOk()
            ->assertJsonPath('collapsed_by_default', true);

        $this->assertDatabaseHas('r_bunny_preferences', [
            'user_id'              => (string) $admin->id,
            'collapsed_by_default' => true,
        ]);
    }

    // ── 8. LGU IDS protected ──────────────────────────────────────

    /** @test */
    public function r_bunny_does_not_interfere_with_lgu_ids_data()
    {
        $tenant = Tenant::create([
            'id' => 'lgu-ids', 'name' => 'LGU IDS', 'slug' => 'lgu-ids', 'status' => 'active',
        ]);
        $admin = $this->createTenantUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->getJson("/api/r-bunny/status?tenant_id=lgu-ids")
            ->assertOk()
            ->assertJsonPath('enabled', true);

        // Verify no leads were modified
        $this->assertDatabaseMissing('leads', ['tenant_id' => 'lgu-ids']);
    }
}
