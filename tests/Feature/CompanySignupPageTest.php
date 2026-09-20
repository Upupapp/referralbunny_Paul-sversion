<?php

namespace Tests\Feature;

use Tests\TestCase;

class CompanySignupPageTest extends TestCase
{
    public function test_both_signup_urls_show_the_direct_account_form(): void
    {
        $this->withoutVite();
        foreach (['/tenant/create', '/signup/build'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Create your company account')
                ->assertSee('name="workspace_name"', false)
                ->assertDontSee('buildWizard')
                ->assertDontSee('name="industry"', false);
        }
    }
}
