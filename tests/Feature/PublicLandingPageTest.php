<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLandingPageTest extends TestCase
{
    public function test_landing_page_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_landing_page_has_exactly_one_h1_with_exact_hero_copy(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertSame(1, preg_match_all('/<h1\b/i', $html));

        preg_match('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $match);
        $h1Text = trim(preg_replace('/\s+/', ' ', strip_tags($match[1] ?? '')));

        $this->assertSame('Launch Your Referral Program in Minutes', $h1Text);
    }

    public function test_landing_page_has_required_ctas(): void
    {
        $response = $this->get('/');

        $response->assertSee('Build a Referral Program');
        $response->assertSee('Join a Referral Program');
        $response->assertSee('See How It Works');

        $this->assertDoesNotMatchRegularExpression('/<(a|button)\b[^>]*>\s*Get Started\s*<\/(a|button)>/i', $response->getContent());
    }

    public function test_landing_page_has_section_anchors(): void
    {
        $html = $this->get('/')->getContent();

        foreach (['how-it-works', 'features', 'use-cases', 'faq', 'pricing'] as $anchor) {
            $this->assertStringContainsString('id="'.$anchor.'"', $html, "Missing #{$anchor} anchor target");
        }

        $this->assertStringContainsString('href="#how-it-works"', $html);
    }

    public function test_landing_page_has_required_seo_tags(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('<title>ReferralBunny.ai — Build and Manage Referral Programs</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://referralbunny.ai/">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('name="twitter:card"', $html);
        $this->assertStringContainsString('"@type": "Organization"', $html);
        $this->assertStringContainsString('"@type": "SoftwareApplication"', $html);
    }

    public function test_landing_page_has_failure_modal_with_login_fallback(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('R Bunny dropped a carrot.', $html);
        $this->assertStringContainsString('rb-modal-open', $html);
    }

    public function test_landing_page_respects_reduced_motion(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('prefers-reduced-motion: reduce', $html);
    }

    public function test_landing_page_image_assets_exist_on_disk(): void
    {
        $html = $this->get('/')->getContent();

        $paths = [];
        preg_match_all('/(?:src|href)="(\/images\/[^"?]+)/', $html, $matches);
        foreach ($matches[1] as $path) {
            $paths[$path] = true;
        }
        preg_match_all('/https:\/\/referralbunny\.ai(\/images\/[^"?]+)/', $html, $matches);
        foreach ($matches[1] as $path) {
            $paths[$path] = true;
        }

        $this->assertNotEmpty($paths);

        foreach (array_keys($paths) as $path) {
            $this->assertFileExists(public_path($path), "Missing asset referenced on landing page: {$path}");
        }
    }

    public function test_signup_build_and_join_aliases_render(): void
    {
        $this->get('/signup/build')->assertStatus(200);
        $this->get('/join')->assertStatus(200);
    }

    public function test_legal_pages_render(): void
    {
        $this->get('/terms')->assertStatus(200);
        $this->get('/privacy')->assertStatus(200);
    }

    public function test_unknown_route_shows_branded_404(): void
    {
        $response = $this->get('/this-page-does-not-exist-rb');

        $response->assertStatus(404);
        $response->assertSee('R Bunny dropped a carrot.');
    }
}
