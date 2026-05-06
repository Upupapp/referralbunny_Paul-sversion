<?php

namespace Tests\Feature;

use App\Mail\TenantInvitationAcceptedMail;
use App\Mail\TenantInvitationExpiredMail;
use App\Mail\TenantInvitationMail;
use App\Mail\TenantInvitationReminderMail;
use App\Mail\TenantInvitationRevokedMail;
use App\Mail\TenantInviterReminderMail;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use App\Services\InvitationReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantManagerInvitationTest extends TestCase
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

    private function createInvitation(Tenant $tenant, TenantUser $inviter, array $attrs = []): TenantInvitation
    {
        return TenantInvitation::create(array_merge([
            'tenant_id'  => $tenant->id,
            'email'      => 'invite-' . Str::random(6) . '@example.com',
            'role'       => 'manager',
            'status'     => 'pending',
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ], $attrs));
    }

    // ── 1. Invite Flow ────────────────────────────────────────────

    /** @test */
    public function admin_can_invite_manager()
    {
        Mail::fake();
        $tenant = $this->createTenant();
        $admin  = $this->createUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->post(route('tenant.users.invite', $tenant->id), [
                'email' => 'newmanager@example.com',
                'role'  => 'manager',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $tenant->id,
            'email'     => 'newmanager@example.com',
            'role'      => 'manager',
            'status'    => 'pending',
        ]);

        Mail::assertSent(TenantInvitationMail::class, fn($m) => $m->invitation->email === 'newmanager@example.com');
    }

    /** @test */
    public function owner_can_invite_admin()
    {
        Mail::fake();
        $tenant = $this->createTenant();
        $owner  = $this->createUser();
        $this->createMembership($tenant, $owner, 'owner');

        $this->actingAs($owner, 'tenant')
            ->post(route('tenant.users.invite', $tenant->id), [
                'email' => 'newadmin@example.com',
                'role'  => 'admin',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_invitations', ['role' => 'admin', 'email' => 'newadmin@example.com']);
    }

    /** @test */
    public function manager_cannot_invite_admin()
    {
        $tenant  = $this->createTenant();
        $manager = $this->createUser();
        $this->createMembership($tenant, $manager, 'manager');

        $this->actingAs($manager, 'tenant')
            ->post(route('tenant.users.invite', $tenant->id), [
                'email' => 'newadmin@example.com',
                'role'  => 'admin',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('role');
    }

    /** @test */
    public function manager_can_invite_member()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $manager = $this->createUser();
        $this->createMembership($tenant, $manager, 'manager');

        $this->actingAs($manager, 'tenant')
            ->post(route('tenant.users.invite', $tenant->id), [
                'email' => 'newmember@example.com',
                'role'  => 'member',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_invitations', ['role' => 'member', 'email' => 'newmember@example.com']);
    }

    /** @test */
    public function cannot_invite_existing_member()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createUser();
        $this->createMembership($tenant, $admin, 'admin');
        $existing = $this->createUser(['email' => 'existing@example.com']);
        $this->createMembership($tenant, $existing, 'member');

        $this->actingAs($admin, 'tenant')
            ->post(route('tenant.users.invite', $tenant->id), [
                'email' => 'existing@example.com',
                'role'  => 'member',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    /** @test */
    public function cannot_invite_duplicate_pending_email()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createUser();
        $this->createMembership($tenant, $admin, 'admin');
        $this->createInvitation($tenant, $admin, ['email' => 'dup@example.com']);

        $this->actingAs($admin, 'tenant')
            ->post(route('tenant.users.invite', $tenant->id), [
                'email' => 'dup@example.com',
                'role'  => 'member',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    /** @test */
    public function cannot_invite_owner_role()
    {
        $tenant = $this->createTenant();
        $admin  = $this->createUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->post(route('tenant.users.invite', $tenant->id), [
                'email' => 'newowner@example.com',
                'role'  => 'owner',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('role');
    }

    /** @test */
    public function invitation_sets_next_reminder_at_on_create()
    {
        Mail::fake();
        $tenant = $this->createTenant();
        $admin  = $this->createUser();
        $this->createMembership($tenant, $admin, 'admin');

        $this->actingAs($admin, 'tenant')
            ->post(route('tenant.users.invite', $tenant->id), [
                'email' => 'newuser@example.com',
                'role'  => 'member',
            ]);

        $invite = TenantInvitation::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($invite->next_reminder_at);
        $this->assertNotNull($invite->next_inviter_reminder_at);
    }

    // ── 2. Accept Flow ────────────────────────────────────────────

    /** @test */
    public function new_user_can_accept_invitation()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $this->createMembership($tenant, $inviter, 'admin');
        $invite  = $this->createInvitation($tenant, $inviter, ['email' => 'brand-new@example.com']);

        $this->post(route('tenant.accept-invite', $invite->token), [
            'first_name'            => 'Brand',
            'last_name'             => 'New',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect();

        $this->assertDatabaseHas('tenant_users', ['email' => 'brand-new@example.com']);
        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'role'      => 'manager',
        ]);
        $this->assertDatabaseHas('tenant_invitations', [
            'id'     => $invite->id,
            'status' => 'accepted',
        ]);
    }

    /** @test */
    public function existing_user_can_accept_invitation()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $this->createMembership($tenant, $inviter, 'admin');
        $existing = $this->createUser(['email' => 'existing@example.com']);
        $invite   = $this->createInvitation($tenant, $inviter, ['email' => 'existing@example.com']);

        $this->post(route('tenant.accept-invite', $invite->token))
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id'      => $tenant->id,
            'tenant_user_id' => $existing->id,
        ]);
    }

    /** @test */
    public function expired_invitation_cannot_be_accepted()
    {
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $invite  = $this->createInvitation($tenant, $inviter, [
            'expires_at' => now()->subDay(),
        ]);

        $this->post(route('tenant.accept-invite', $invite->token), [
            'first_name'            => 'Test',
            'last_name'             => 'User',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('tenant.login'));
    }

    /** @test */
    public function accept_suppresses_reminders()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $this->createMembership($tenant, $inviter, 'admin');
        $invite  = $this->createInvitation($tenant, $inviter, ['email' => 'suppress@example.com']);
        $invite->update(['next_reminder_at' => now()->addHours(2)]);

        $this->post(route('tenant.accept-invite', $invite->token), [
            'first_name'            => 'Test',
            'last_name'             => 'User',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $invite->refresh();
        $this->assertNotNull($invite->reminder_suppressed_at);
        $this->assertNull($invite->next_reminder_at);
        $this->assertNull($invite->next_inviter_reminder_at);
    }

    /** @test */
    public function accept_sends_accepted_email_to_inviter()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $inviter = $this->createUser(['email' => 'inviter@example.com']);
        $this->createMembership($tenant, $inviter, 'admin');
        $invite  = $this->createInvitation($tenant, $inviter, ['email' => 'newone@example.com']);

        $this->post(route('tenant.accept-invite', $invite->token), [
            'first_name'            => 'New',
            'last_name'             => 'One',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        Mail::assertSent(TenantInvitationAcceptedMail::class,
            fn($m) => $m->inviter->email === 'inviter@example.com'
        );
    }

    // ── 3. Resend & Revoke ────────────────────────────────────────

    /** @test */
    public function admin_can_resend_invitation()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $admin   = $this->createUser();
        $this->createMembership($tenant, $admin, 'admin');
        $invite  = $this->createInvitation($tenant, $admin);

        $this->actingAs($admin, 'tenant')
            ->post(route('tenant.invitations.resend', [$tenant->id, $invite->id]))
            ->assertRedirect();

        Mail::assertSent(TenantInvitationMail::class);
    }

    /** @test */
    public function resend_respects_24h_rate_limit()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $admin   = $this->createUser();
        $this->createMembership($tenant, $admin, 'admin');
        $invite  = $this->createInvitation($tenant, $admin);
        $invite->update(['last_manual_resend_at' => now()->subMinutes(30)]);

        $this->actingAs($admin, 'tenant')
            ->post(route('tenant.invitations.resend', [$tenant->id, $invite->id]))
            ->assertRedirect()
            ->assertSessionHasErrors('resend');
    }

    /** @test */
    public function admin_can_revoke_invitation()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $admin   = $this->createUser();
        $this->createMembership($tenant, $admin, 'admin');
        $invite  = $this->createInvitation($tenant, $admin);

        $this->actingAs($admin, 'tenant')
            ->delete(route('tenant.invitations.revoke', [$tenant->id, $invite->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_invitations', ['id' => $invite->id, 'status' => 'revoked']);
        Mail::assertSent(TenantInvitationRevokedMail::class);
    }

    /** @test */
    public function revoke_suppresses_reminders()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $admin   = $this->createUser();
        $this->createMembership($tenant, $admin, 'admin');
        $invite  = $this->createInvitation($tenant, $admin);
        $invite->update([
            'next_reminder_at'         => now()->addHours(2),
            'next_inviter_reminder_at' => now()->addHours(3),
        ]);

        $this->actingAs($admin, 'tenant')
            ->delete(route('tenant.invitations.revoke', [$tenant->id, $invite->id]));

        $invite->refresh();
        $this->assertNull($invite->next_reminder_at);
        $this->assertNull($invite->next_inviter_reminder_at);
    }

    // ── 4. Send Reminder Now ──────────────────────────────────────

    /** @test */
    public function admin_can_send_manual_reminder()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $admin   = $this->createUser();
        $this->createMembership($tenant, $admin, 'admin');
        $invite  = $this->createInvitation($tenant, $admin);

        $this->actingAs($admin, 'tenant')
            ->post(route('tenant.invitations.remind-now', [$tenant->id, $invite->id]))
            ->assertRedirect();

        Mail::assertSent(TenantInvitationReminderMail::class);
    }

    /** @test */
    public function manual_reminder_respects_24h_rate_limit()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $admin   = $this->createUser();
        $this->createMembership($tenant, $admin, 'admin');
        $invite  = $this->createInvitation($tenant, $admin);
        $invite->update(['last_manual_resend_at' => now()->subMinutes(60)]);

        $this->actingAs($admin, 'tenant')
            ->post(route('tenant.invitations.remind-now', [$tenant->id, $invite->id]))
            ->assertRedirect()
            ->assertSessionHasErrors('reminder');
    }

    // ── 5. InvitationReminderService ─────────────────────────────

    /** @test */
    public function service_sends_invitee_reminder_when_due()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $invite  = $this->createInvitation($tenant, $inviter);
        $invite->update(['next_reminder_at' => now()->subMinute()]);

        $service = app(InvitationReminderService::class);
        $sent    = $service->processInviteeReminders();

        $this->assertEquals(1, $sent);
        Mail::assertSent(TenantInvitationReminderMail::class);

        $invite->refresh();
        $this->assertEquals(1, $invite->reminder_count);
    }

    /** @test */
    public function service_does_not_send_reminder_when_not_due()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $invite  = $this->createInvitation($tenant, $inviter);
        $invite->update(['next_reminder_at' => now()->addHours(5)]);

        $service = app(InvitationReminderService::class);
        $sent    = $service->processInviteeReminders();

        $this->assertEquals(0, $sent);
        Mail::assertNotSent(TenantInvitationReminderMail::class);
    }

    /** @test */
    public function service_stops_after_max_invitee_reminders()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $invite  = $this->createInvitation($tenant, $inviter);
        $invite->update([
            'reminder_count'   => 3,
            'next_reminder_at' => now()->subMinute(),
        ]);

        $service = app(InvitationReminderService::class);
        $sent    = $service->processInviteeReminders();

        $this->assertEquals(0, $sent);
    }

    /** @test */
    public function service_skips_suppressed_invitations()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $invite  = $this->createInvitation($tenant, $inviter);
        $invite->update([
            'next_reminder_at'       => now()->subMinute(),
            'reminder_suppressed_at' => now(),
        ]);

        $service = app(InvitationReminderService::class);
        $sent    = $service->processInviteeReminders();

        $this->assertEquals(0, $sent);
    }

    /** @test */
    public function service_sends_inviter_reminder_when_due()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $this->createMembership($tenant, $inviter, 'admin');
        $invite  = $this->createInvitation($tenant, $inviter);
        $invite->update(['next_inviter_reminder_at' => now()->subMinute()]);

        $service = app(InvitationReminderService::class);
        $sent    = $service->processInviterReminders();

        $this->assertGreaterThanOrEqual(1, $sent);
        $invite->refresh();
        $this->assertEquals(1, $invite->inviter_reminder_count);
    }

    /** @test */
    public function service_sends_inviter_email_on_second_reminder()
    {
        Mail::fake();
        $tenant  = $this->createTenant();
        $inviter = $this->createUser(['email' => 'inviter@example.com']);
        $this->createMembership($tenant, $inviter, 'admin');
        $invite  = $this->createInvitation($tenant, $inviter);
        $invite->update([
            'inviter_reminder_count'   => 1,
            'next_inviter_reminder_at' => now()->subMinute(),
        ]);

        $service = app(InvitationReminderService::class);
        $service->processInviterReminders();

        Mail::assertSent(TenantInviterReminderMail::class,
            fn($m) => $m->inviter->email === 'inviter@example.com'
        );
    }

    /** @test */
    public function initialise_schedule_sets_both_reminder_timestamps()
    {
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $invite  = $this->createInvitation($tenant, $inviter);

        $service = app(InvitationReminderService::class);
        $service->initialiseSchedule($invite);

        $invite->refresh();
        $this->assertNotNull($invite->next_reminder_at);
        $this->assertNotNull($invite->next_inviter_reminder_at);
        $this->assertNotNull($invite->initial_email_sent_at);
    }

    /** @test */
    public function manual_resend_resets_reminder_schedule()
    {
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $invite  = $this->createInvitation($tenant, $inviter);
        $invite->update([
            'reminder_count'   => 2,
            'next_reminder_at' => null,
        ]);

        $service = app(InvitationReminderService::class);
        $result  = $service->manualResend($invite);

        $this->assertTrue($result);
        $invite->refresh();
        $this->assertEquals(0, $invite->reminder_count);
        $this->assertNotNull($invite->next_reminder_at);
    }

    /** @test */
    public function manual_resend_rejects_within_24h_window()
    {
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $invite  = $this->createInvitation($tenant, $inviter);
        $invite->update(['last_manual_resend_at' => now()->subMinutes(30)]);

        $service = app(InvitationReminderService::class);
        $result  = $service->manualResend($invite);

        $this->assertFalse($result);
    }

    /** @test */
    public function suppress_clears_next_reminder_timestamps()
    {
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $invite  = $this->createInvitation($tenant, $inviter);
        $invite->update([
            'next_reminder_at'         => now()->addHour(),
            'next_inviter_reminder_at' => now()->addHours(2),
        ]);

        $service = app(InvitationReminderService::class);
        $service->suppress($invite, 'test');

        $invite->refresh();
        $this->assertNull($invite->next_reminder_at);
        $this->assertNull($invite->next_inviter_reminder_at);
        $this->assertNotNull($invite->reminder_suppressed_at);
    }

    // ── 6. Token & Security ───────────────────────────────────────

    /** @test */
    public function invitation_token_is_unique_and_random()
    {
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $a = $this->createInvitation($tenant, $inviter);
        $b = $this->createInvitation($tenant, $inviter, ['email' => 'other@example.com']);

        $this->assertNotEquals($a->token, $b->token);
        $this->assertEquals(48, strlen($a->token));
    }

    /** @test */
    public function invalid_token_shows_expired_page()
    {
        $this->get(route('tenant.accept-invite.show', 'invalid-token-xyz'))
            ->assertOk()
            ->assertViewIs('auth.tenant-invite-expired');
    }

    /** @test */
    public function invitation_page_shows_for_valid_token()
    {
        $tenant  = $this->createTenant();
        $inviter = $this->createUser();
        $invite  = $this->createInvitation($tenant, $inviter);

        $this->get(route('tenant.accept-invite.show', $invite->token))
            ->assertOk()
            ->assertViewIs('auth.tenant-accept-invite');
    }

    // ── 7. Role Isolation ─────────────────────────────────────────

    /** @test */
    public function member_cannot_invite_anyone()
    {
        $tenant = $this->createTenant();
        $member = $this->createUser();
        $this->createMembership($tenant, $member, 'member');

        $this->actingAs($member, 'tenant')
            ->post(route('tenant.users.invite', $tenant->id), [
                'email' => 'newuser@example.com',
                'role'  => 'viewer',
            ])
            ->assertForbidden();
    }

    /** @test */
    public function cross_tenant_invitation_is_blocked()
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $admin   = $this->createUser();
        $this->createMembership($tenantA, $admin, 'admin');
        $invite  = $this->createInvitation($tenantB, $admin);

        // Admin of tenant A cannot resend invite from tenant B
        $this->actingAs($admin, 'tenant')
            ->post(route('tenant.invitations.resend', [$tenantA->id, $invite->id]))
            ->assertNotFound();
    }
}
