<?php

namespace Tests\Feature;

use App\Models\Reseller;
use App\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortalViewSwitchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);
        $this->withoutVite();
        Schema::create('tenant_users', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('email'); $t->string('first_name');
            $t->string('last_name'); $t->string('status'); $t->rememberToken();
        });
        Schema::create('tenant_memberships', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('tenant_user_id');
            $t->string('tenant_id'); $t->string('role'); $t->string('status');
        });
        Schema::create('resellers', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('tenant_id'); $t->string('name');
            $t->string('email'); $t->string('status'); $t->string('linked_tenant_user_id')->nullable();
            $t->boolean('is_anonymous')->default(false); $t->integer('assigned_leads')->default(0);
            $t->decimal('closed_value')->default(0); $t->integer('performance_score')->default(0);
            $t->date('joined_date')->nullable(); $t->rememberToken(); $t->softDeletes(); $t->timestamps();
        });
        DB::table('tenant_users')->insert(['id' => 'admin', 'email' => 'admin@example.com', 'first_name' => 'Jamie', 'last_name' => 'Lee', 'status' => 'active']);
        DB::table('tenant_memberships')->insert(['id' => 'membership', 'tenant_user_id' => 'admin', 'tenant_id' => 'company', 'role' => 'owner', 'status' => 'active']);
    }

    private function referrer(array $extra = []): Reseller
    {
        DB::table('resellers')->insert(array_merge(['id' => 'referrer', 'tenant_id' => 'company', 'name' => 'Jamie', 'email' => 'admin@example.com', 'status' => 'active', 'linked_tenant_user_id' => 'admin'], $extra));
        return Reseller::findOrFail('referrer');
    }

    public function test_admin_can_switch_both_ways_without_password_and_only_one_guard_is_active(): void
    {
        $this->referrer();
        $this->actingAs(TenantUser::find('admin'), 'tenant');
        $this->post('/portal/switch-view', ['view' => 'referrer', 'tenant_id' => 'company'])->assertRedirect(route('reseller.dashboard', 'company'));
        $this->assertAuthenticated('reseller'); $this->assertGuest('tenant');
        $this->post('/portal/switch-view', ['view' => 'admin'])->assertRedirect(route('tenant.dashboard', 'company'));
        $this->assertAuthenticated('tenant'); $this->assertGuest('reseller');
    }

    public function test_first_switch_creates_linked_referrer_once(): void
    {
        $this->actingAs(TenantUser::find('admin'), 'tenant');
        $this->post('/portal/switch-view', ['view' => 'referrer', 'tenant_id' => 'company'])->assertRedirect();
        $this->assertDatabaseHas('resellers', ['linked_tenant_user_id' => 'admin', 'status' => 'active']);
        $this->post('/portal/switch-view', ['view' => 'admin'])->assertRedirect();
        $this->post('/portal/switch-view', ['view' => 'referrer', 'tenant_id' => 'company'])->assertRedirect();
        $this->assertDatabaseCount('resellers', 1);
    }

    public function test_unlinked_referrer_cannot_gain_admin_access_by_matching_email(): void
    {
        $this->actingAs($this->referrer(['linked_tenant_user_id' => null]), 'reseller');
        $this->post('/portal/switch-view', ['view' => 'admin'])->assertRedirect(route('portal.my-programs'));
        $this->assertGuest('tenant');
        $this->get('/portal/my-programs')->assertOk()->assertSee('Create a company');
    }

    public function test_suspended_referrer_cannot_be_reactivated_by_switching(): void
    {
        $this->referrer(['status' => 'suspended']);
        $this->actingAs(TenantUser::find('admin'), 'tenant');
        $this->post('/portal/switch-view', ['view' => 'referrer', 'tenant_id' => 'company'])->assertForbidden();
        $this->assertGuest('reseller');
    }

    public function test_existing_referrals_in_another_program_are_preserved(): void
    {
        $this->referrer(['tenant_id' => 'referral-program']);
        $this->actingAs(TenantUser::find('admin'), 'tenant');
        $this->post('/portal/switch-view', ['view' => 'referrer', 'tenant_id' => 'company'])
            ->assertRedirect(route('reseller.dashboard', 'referral-program'));
        $this->assertDatabaseCount('resellers', 1);
        $this->post('/portal/switch-view', ['view' => 'admin'])->assertRedirect(route('tenant.dashboard', 'company'));
    }

    public function test_switch_does_not_grant_access_to_another_company(): void
    {
        $this->actingAs(TenantUser::find('admin'), 'tenant');
        $this->post('/portal/switch-view', ['view' => 'referrer', 'tenant_id' => 'other-company'])->assertNotFound();
        $this->assertDatabaseCount('resellers', 0);
    }
}
