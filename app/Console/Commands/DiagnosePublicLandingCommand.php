<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Throwable;

class DiagnosePublicLandingCommand extends Command
{
    protected $signature = 'referralbunny:diagnose-public-landing
        {--routes : Check public landing, signup/join aliases, and legal routes resolve}
        {--assets : Check every /images/* asset referenced on the page exists on disk}
        {--ctas : Check the 3 required CTA labels and the #how-it-works anchor}
        {--seo : Check title/meta/canonical/robots/OG/Twitter/JSON-LD output}
        {--performance : Check <img> tags have loading=lazy|eager and modern formats}
        {--accessibility : Check single exact H1, alt text, skip link, mobile menu aria}
        {--fallbacks : Check the failure modal and its route fallbacks are present}
        {--analytics : Check GA config and rbTrack wiring}
        {--ui : Check all section partials exist and the page renders}
        {--repair-safe : Clear route/view/config caches (no data writes)}';

    protected $description = 'Diagnose the public landing page: routes, assets, CTAs, SEO, performance, accessibility, fallbacks, analytics, and UI.';

    private int $failures = 0;

    public function handle(): int
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info(' ReferralBunny.ai — Public Landing Page Diagnostics');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $any = $this->option('routes') || $this->option('assets') || $this->option('ctas')
            || $this->option('seo') || $this->option('performance') || $this->option('accessibility')
            || $this->option('fallbacks') || $this->option('analytics') || $this->option('ui');

        $runAll = ! $any;

        $needsHtml = $runAll || $this->option('assets') || $this->option('ctas') || $this->option('seo')
            || $this->option('performance') || $this->option('accessibility') || $this->option('fallbacks')
            || $this->option('analytics') || $this->option('ui');

        $html = null;
        $renderError = null;

        if ($needsHtml) {
            try {
                $html = view('public.home')->render();
            } catch (Throwable $e) {
                $renderError = $e;
                $this->error('Failed to render public.home: '.$e->getMessage());
                $this->failures++;
                $this->newLine();
            }
        }

        if ($runAll || $this->option('routes')) {
            $this->checkRoutes();
        }

        if ($runAll || $this->option('ui')) {
            $this->checkUi($html, $renderError);
        }

        if ($html !== null) {
            if ($runAll || $this->option('assets')) {
                $this->checkAssets($html);
            }

            if ($runAll || $this->option('ctas')) {
                $this->checkCtas($html);
            }

            if ($runAll || $this->option('seo')) {
                $this->checkSeo($html);
            }

            if ($runAll || $this->option('performance')) {
                $this->checkPerformance($html);
            }

            if ($runAll || $this->option('accessibility')) {
                $this->checkAccessibility($html);
            }

            if ($runAll || $this->option('fallbacks')) {
                $this->checkFallbacks($html);
            }

            if ($runAll || $this->option('analytics')) {
                $this->checkAnalytics($html);
            }
        }

        if ($this->option('repair-safe')) {
            $this->repairSafe();
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        if ($this->failures === 0) {
            $this->info(' All checks passed.');
        } else {
            $this->error(" {$this->failures} check(s) failed — see MISSING/FAIL lines above.");
        }
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        return $this->failures === 0 ? 0 : 1;
    }

    private function checkRoutes(): void
    {
        $this->info('── Routes ───────────────────────────────────────────────────');

        $routes = [
            'public.home' => 'Public landing page (/)',
            'signup.build' => 'Build a Referral Program alias',
            'join' => 'Join a Referral Program alias',
            'terms' => 'Terms of Service',
            'privacy' => 'Privacy Policy',
            'tenant.create' => 'Existing build flow (unchanged)',
            'tenant.join' => 'Existing join flow (unchanged)',
            'login' => 'Login',
        ];

        foreach ($routes as $name => $label) {
            $ok = Route::has($name);
            $this->line(($ok ? '  ok      ' : '  MISSING ')."{$name}  ({$label})");
            if (! $ok) {
                $this->failures++;
            }
        }

        $this->newLine();
    }

    private function checkUi(?string $html, ?Throwable $renderError): void
    {
        $this->info('── UI: section partials ─────────────────────────────────────');

        $sections = [
            'header', 'hero', 'problem', 'solution', 'how-it-works', 'product-preview',
            'benefits', 'features', 'use-cases', 'r-bunny-helper', 'automations',
            'security', 'setup-wizard', 'product-proof', 'pricing-preview', 'faq',
            'final-cta', 'footer',
        ];

        foreach ($sections as $section) {
            $ok = View::exists("public.sections.{$section}");
            $this->line(($ok ? '  ok      ' : '  MISSING ')."public.sections.{$section}");
            if (! $ok) {
                $this->failures++;
            }
        }

        if ($renderError) {
            $this->line('  FAIL     public.home failed to render — '.$renderError->getMessage());
            $this->failures++;
        } elseif ($html !== null) {
            $this->line('  ok      public.home rendered ('.strlen($html).' bytes)');

            foreach (['how-it-works', 'features', 'use-cases', 'faq', 'pricing'] as $anchor) {
                $ok = str_contains($html, 'id="'.$anchor.'"');
                $this->line(($ok ? '  ok      ' : '  MISSING ')."section anchor #{$anchor}");
                if (! $ok) {
                    $this->failures++;
                }
            }
        }

        $this->newLine();
    }

    private function checkAssets(string $html): void
    {
        $this->info('── Assets ───────────────────────────────────────────────────');

        $paths = [];

        // <img src="/images/...">, favicon/apple-touch-icon href="/images/...".
        // Stops at `?` so r-bunny's `?v={mtime}` cache-busting suffix is dropped.
        preg_match_all('/(?:src|href)="(\/images\/[^"?]+)/', $html, $matches);
        foreach ($matches[1] as $path) {
            $paths[$path] = true;
        }

        // Absolute OG/Twitter image URLs.
        preg_match_all('/https:\/\/referralbunny\.ai(\/images\/[^"?]+)/', $html, $matches);
        foreach ($matches[1] as $path) {
            $paths[$path] = true;
        }

        $paths = array_keys($paths);
        sort($paths);

        foreach ($paths as $path) {
            $ok = file_exists(public_path($path));
            $this->line(($ok ? '  ok      ' : '  MISSING ').$path);
            if (! $ok) {
                $this->failures++;
            }
        }

        if (empty($paths)) {
            $this->line('  FAIL     no /images/ asset references found in rendered output');
            $this->failures++;
        }

        $this->newLine();
    }

    private function checkCtas(string $html): void
    {
        $this->info('── CTAs ─────────────────────────────────────────────────────');

        foreach (['Build a Referral Program', 'Join a Referral Program', 'See How It Works'] as $label) {
            $count = substr_count($html, $label);
            $ok = $count > 0;
            $this->line(($ok ? '  ok      ' : '  MISSING ')."\"{$label}\" ({$count}x)");
            if (! $ok) {
                $this->failures++;
            }
        }

        $anchorOk = str_contains($html, 'href="#how-it-works"') && str_contains($html, 'id="how-it-works"');
        $this->line(($anchorOk ? '  ok      ' : '  MISSING ').'#how-it-works anchor link + target');
        if (! $anchorOk) {
            $this->failures++;
        }

        $genericGetStarted = (bool) preg_match('/<(a|button)\b[^>]*>\s*Get Started\s*<\/(a|button)>/i', $html);
        $this->line((! $genericGetStarted ? '  ok      ' : '  FAIL    ').'no generic "Get Started" CTA button/link');
        if ($genericGetStarted) {
            $this->failures++;
        }

        $this->newLine();
    }

    private function checkSeo(string $html): void
    {
        $this->info('── SEO ──────────────────────────────────────────────────────');

        $checks = [
            'exact <title>' => str_contains($html, '<title>ReferralBunny.ai — Build and Manage Referral Programs</title>'),
            'meta description present' => (bool) preg_match('/<meta name="description" content="[^"]{20,}"/', $html),
            'canonical https://referralbunny.ai/' => str_contains($html, '<link rel="canonical" href="https://referralbunny.ai/">'),
            'robots: index, follow' => str_contains($html, '<meta name="robots" content="index, follow">'),
            'og:title' => str_contains($html, 'property="og:title"'),
            'og:description' => str_contains($html, 'property="og:description"'),
            'og:image' => str_contains($html, 'property="og:image"'),
            'og:url' => str_contains($html, 'property="og:url"'),
            'twitter:card' => str_contains($html, 'name="twitter:card"'),
            'twitter:title' => str_contains($html, 'name="twitter:title"'),
            'JSON-LD Organization' => str_contains($html, '"@type": "Organization"'),
            'JSON-LD SoftwareApplication' => str_contains($html, '"@type": "SoftwareApplication"'),
            'no fake rating/review JSON-LD' => ! (bool) preg_match('/"aggregateRating"|"review"/i', $html),
        ];

        foreach ($checks as $label => $ok) {
            $this->line(($ok ? '  ok      ' : '  MISSING ').$label);
            if (! $ok) {
                $this->failures++;
            }
        }

        $this->newLine();
    }

    private function checkPerformance(string $html): void
    {
        $this->info('── Performance ──────────────────────────────────────────────');

        preg_match_all('/<img\b[^>]*>/i', $html, $imgMatches);
        $images = $imgMatches[0];

        $missingLoading = 0;
        $nonModernFormat = 0;

        foreach ($images as $img) {
            if (! preg_match('/loading="(lazy|eager)"/', $img)) {
                $missingLoading++;
            }

            if (preg_match('/src="([^"]+)"/', $img, $srcMatch)) {
                $src = strtok($srcMatch[1], '?');
                if (! preg_match('/\.(webp|avif|svg)$/i', $src)) {
                    $nonModernFormat++;
                }
            }
        }

        $this->line('  info     '.count($images).' <img> tag(s) found');

        $ok = $missingLoading === 0;
        $this->line(($ok ? '  ok      ' : '  FAIL    ')."{$missingLoading} <img> missing loading=lazy|eager");
        if (! $ok) {
            $this->failures++;
        }

        $ok = $nonModernFormat === 0;
        $this->line(($ok ? '  ok      ' : '  FAIL    ')."{$nonModernFormat} <img> not webp/avif/svg");
        if (! $ok) {
            $this->failures++;
        }

        $reducedMotion = str_contains($html, 'prefers-reduced-motion: reduce');
        $this->line(($reducedMotion ? '  ok      ' : '  MISSING ').'prefers-reduced-motion override present');
        if (! $reducedMotion) {
            $this->failures++;
        }

        $this->newLine();
    }

    private function checkAccessibility(string $html): void
    {
        $this->info('── Accessibility ────────────────────────────────────────────');

        $h1Count = preg_match_all('/<h1\b/i', $html);
        $ok = $h1Count === 1;
        $this->line(($ok ? '  ok      ' : '  FAIL    ')."exactly one <h1> (found {$h1Count})");
        if (! $ok) {
            $this->failures++;
        }

        $h1TextOk = false;
        if (preg_match('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $h1Match)) {
            $h1Text = trim(preg_replace('/\s+/', ' ', strip_tags($h1Match[1])));
            $h1TextOk = $h1Text === 'Launch Your Referral Program in Minutes';
        }
        $this->line(($h1TextOk ? '  ok      ' : '  MISSING ').'H1 text is exact hero headline');
        if (! $h1TextOk) {
            $this->failures++;
        }

        $imgCount = preg_match_all('/<img\b/i', $html);
        $altCount = preg_match_all('/<img\b[^>]*\salt="/i', $html);
        $ok = $imgCount > 0 && $imgCount === $altCount;
        $this->line(($ok ? '  ok      ' : '  FAIL    ')."all <img> have alt= ({$altCount}/{$imgCount})");
        if (! $ok) {
            $this->failures++;
        }

        $skip = str_contains($html, 'href="#main-content"') && str_contains($html, 'id="main-content"');
        $this->line(($skip ? '  ok      ' : '  MISSING ').'skip link to #main-content');
        if (! $skip) {
            $this->failures++;
        }

        $menuAria = str_contains($html, 'aria-controls="mobile-menu-panel"') && str_contains($html, 'aria-expanded');
        $this->line(($menuAria ? '  ok      ' : '  MISSING ').'mobile menu aria-controls/aria-expanded');
        if (! $menuAria) {
            $this->failures++;
        }

        $this->newLine();
    }

    private function checkFallbacks(string $html): void
    {
        $this->info('── Fallbacks ────────────────────────────────────────────────');

        $modal = str_contains($html, 'R Bunny dropped a carrot.') && str_contains($html, 'rb-modal-open');
        $this->line(($modal ? '  ok      ' : '  MISSING ').'failure modal markup (<x-public.failure-modal>)');
        if (! $modal) {
            $this->failures++;
        }

        $loginOk = Route::has('login');
        $this->line(($loginOk ? '  ok      ' : '  MISSING ').'login route resolves (failure-modal fallback)');
        if (! $loginOk) {
            $this->failures++;
        }

        $buildOk = Route::has('signup.build');
        $this->line(($buildOk ? '  ok      ' : '  MISSING ').'signup.build route resolves (failure-modal primary CTA)');
        if (! $buildOk) {
            $this->failures++;
        }

        $this->newLine();
    }

    private function checkAnalytics(string $html): void
    {
        $this->info('── Analytics ────────────────────────────────────────────────');

        $gaId = config('services.google_analytics_id');
        $this->line('  info     services.google_analytics_id = '.($gaId ? '(set)' : '(not set)'));

        $rbTrack = str_contains($html, 'window.rbTrack');
        $this->line(($rbTrack ? '  ok      ' : '  MISSING ').'window.rbTrack helper present');
        if (! $rbTrack) {
            $this->failures++;
        }

        $bundleLoaded = (bool) preg_match('/<script[^>]+src="[^"]*public-landing[^"]*"/', $html);
        $this->line(($bundleLoaded ? '  ok      ' : '  MISSING ').'public-landing.js bundle loaded (wires data-rb-event -> rbTrack)');
        if (! $bundleLoaded) {
            $this->failures++;
        }

        $ctaEvents = preg_match_all('/data-rb-event="[^"]+"/', $html);
        $ok = $ctaEvents > 1;
        $this->line(($ok ? '  ok      ' : '  MISSING ')."data-rb-event attributes on CTAs ({$ctaEvents} found)");
        if (! $ok) {
            $this->failures++;
        }

        $viewEvents = preg_match_all('/data-rb-view-event="[^"]+"/', $html);
        $ok = $viewEvents >= 3;
        $this->line(($ok ? '  ok      ' : '  MISSING ')."data-rb-view-event attributes ({$viewEvents} found)");
        if (! $ok) {
            $this->failures++;
        }

        $this->newLine();
    }

    /**
     * Clears framework caches only (route/view/config). Never touches tenant
     * data, commission/pricing formulas, pipeline stage rules, deal/import
     * logic, or any lgu-ids-conditional code path.
     */
    private function repairSafe(): void
    {
        $this->info('── Repair (safe) ────────────────────────────────────────────');

        foreach (['route:clear', 'view:clear', 'config:clear'] as $command) {
            Artisan::call($command);
            $this->line('  ran      php artisan '.$command);
        }

        $this->newLine();
    }
}
