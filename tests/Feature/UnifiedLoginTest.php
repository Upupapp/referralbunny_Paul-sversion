<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\TenantAuthWebController;
use App\Http\Controllers\ResellerPortalAuthController;
use App\Http\Controllers\Web\PartnerAuthController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UnifiedLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);
        $this->withoutVite();
        // Isolate credential routing from the legacy, incomplete migration history.
        foreach (['users', 'tenant_users', 'resellers', 'partner_users'] as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->string('email');
                $table->string('password')->nullable();
                $table->string('status')->default('active');
                $table->rememberToken();
                $table->softDeletes();
            });
        }
    }

    public function test_every_login_entry_shows_the_same_form(): void
    {
        foreach (['/login', '/sign-in', '/tenant/login', '/reseller/login', '/partner/login'] as $url) {
            $this->get($url)->assertOk()->assertViewIs('auth.login')
                ->assertSee('action="'.route('login').'"', false)
                ->assertDontSee('Super Admin Portal');
        }
    }

    public function test_platform_credentials_sign_in_and_override_another_portals_intended_url(): void
    {
        DB::table('users')->insert(['email' => 'ADMIN@example.com', 'password' => Hash::make('correct-password')]);
        $this->withSession(['url.intended' => '/tenant/wrong/dashboard'])
            ->post('/login', ['email' => 'admin@example.com', 'password' => 'correct-password'])
            ->assertRedirect(route('platform.dashboard'));
        $this->assertAuthenticated('web');
        $this->assertGuest('tenant');
    }

    public function test_role_detection_delegates_to_existing_portal_security_flows(): void
    {
        foreach ([
            ['tenant_users', TenantAuthWebController::class],
            ['resellers', ResellerPortalAuthController::class],
            ['partner_users', PartnerAuthController::class],
        ] as [$table, $controller]) {
            DB::table($table)->insert(['email' => $table.'@example.com', 'password' => Hash::make('correct-password')]);
            $this->mock($controller)->shouldReceive('login')->once()->andReturn(redirect('/verified-'.$table));
            $this->post('/login', ['email' => $table.'@example.com', 'password' => 'correct-password'])
                ->assertRedirect('/verified-'.$table);
        }
    }

    public function test_matching_email_with_wrong_password_does_not_choose_a_privileged_role(): void
    {
        DB::table('users')->insert(['email' => 'shared@example.com', 'password' => Hash::make('admin-password')]);
        DB::table('resellers')->insert(['email' => 'shared@example.com', 'password' => Hash::make('referrer-password')]);
        $this->mock(ResellerPortalAuthController::class)->shouldReceive('login')->once()->andReturn(redirect('/referrer'));
        $this->post('/login', ['email' => 'shared@example.com', 'password' => 'referrer-password'])->assertRedirect('/referrer');
        $this->assertGuest('web');
    }

    public function test_inactive_and_invalid_credentials_are_rejected_without_flashing_password(): void
    {
        DB::table('tenant_users')->insert(['email' => 'inactive@example.com', 'password' => Hash::make('correct-password'), 'status' => 'suspended']);
        foreach (['inactive@example.com', 'unknown@example.com'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'correct-password'])
                ->assertRedirect(route('login'))->assertSessionHasErrors('email')
                ->assertSessionMissing('_old_input.password');
            $this->assertGuest('tenant');
            $this->assertGuest('web');
        }
    }
}
