<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanySignupTest extends TestCase
{
    use RefreshDatabase;

    private function account(): array
    {
        return [
            'first_name' => 'Jamie',
            'last_name' => 'Lee',
            'workspace_name' => 'New Company',
            'email' => 'jamie@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'terms' => '1',
        ];
    }

    public function test_company_can_sign_up_without_program_questions(): void
    {
        $response = $this->post('/tenant/create', $this->account());
        $response->assertSessionHasNoErrors();
        $tenant = DB::table('tenants')->where('name', 'New Company')->first();
        $this->assertNotNull($tenant);
        $response->assertRedirect(route('tenant.dashboard', $tenant->id));
        $this->assertAuthenticated('tenant');
        $this->assertDatabaseHas('tenant_memberships', ['tenant_id' => $tenant->id, 'role' => 'owner', 'status' => 'active']);
        $this->assertDatabaseHas('tenant_program_configs', ['tenant_id' => $tenant->id, 'onboarding_complete' => true]);
        $this->assertDatabaseHas('tenant_pipeline_stages', ['tenant_id' => $tenant->id, 'is_won' => true, 'is_final' => true]);
        $this->assertTrue(Hash::check('secure-password', DB::table('tenant_users')->where('email', 'jamie@example.com')->value('password')));
    }

    public function test_signup_still_requires_matching_password_and_terms(): void
    {
        $data = $this->account();
        $data['password_confirmation'] = 'different-password';
        unset($data['terms']);
        $this->post('/tenant/create', $data)->assertSessionHasErrors(['password', 'terms']);
        $this->assertDatabaseMissing('tenant_users', ['email' => 'jamie@example.com']);
    }

}
