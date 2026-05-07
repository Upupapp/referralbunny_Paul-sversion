<?php

namespace App\Services\QA;

use Illuminate\Support\Facades\Route;

/**
 * Checks route registrations, middleware coverage, and controller binding.
 */
class QaRouteChecker
{
    /** Expected middleware on tenant-facing routes */
    private const TENANT_MIDDLEWARE = ['auth:tenant,web', 'tenant.access'];

    /** Expected middleware on reseller-facing routes */
    private const RESELLER_MIDDLEWARE = ['auth:reseller,web', 'reseller.access'];

    /** Expected middleware on partner-facing routes */
    private const PARTNER_MIDDLEWARE = ['auth:partner', 'partner.access'];

    /** Critical routes that MUST exist */
    private const REQUIRED_ROUTES = [
        'tenant.login'         => '/tenant/login',
        'reseller.login'       => '/reseller/login',
        'partner.login'        => '/partner/login',
        'reseller.forgot-password' => '/reseller/forgot-password',
        'partner.forgot-password'  => '/partner/forgot-password',
        'reseller.setup'       => '/reseller/setup',
        'partner.setup'        => '/partner/setup',
    ];

    public function run(?string $module = null): array
    {
        $results = [];

        $results = array_merge($results, $this->checkRequiredNamedRoutes());
        $results = array_merge($results, $this->checkControllerBindings());
        $results = array_merge($results, $this->checkMiddlewareCoverage());
        $results = array_merge($results, $this->checkDuplicateRoutes());

        return $results;
    }

    private function checkRequiredNamedRoutes(): array
    {
        $results = [];

        foreach (self::REQUIRED_ROUTES as $name => $path) {
            if (Route::has($name)) {
                $results[] = $this->pass("route.named.{$name}", "Named route '{$name}' exists", 'routes');
            } else {
                $results[] = $this->critical("route.named.{$name}", "Named route '{$name}' is MISSING — auth/invite flows may break", 'routes');
            }
        }

        return $results;
    }

    private function checkControllerBindings(): array
    {
        $results = [];
        $broken  = 0;

        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction();
            if (!isset($action['controller'])) continue;

            [$class, $method] = array_pad(explode('@', $action['controller']), 2, '__invoke');

            if (!class_exists($class)) {
                $broken++;
                $results[] = $this->fail(
                    "route.controller.{$class}",
                    "Controller class not found: {$class} on route {$route->uri()}",
                    'routes'
                );
            }
        }

        if ($broken === 0) {
            $results[] = $this->pass('route.controllers.all', 'All route controllers resolve to existing classes', 'routes');
        }

        return $results;
    }

    private function checkMiddlewareCoverage(): array
    {
        $results   = [];
        $unguarded = [];

        foreach (Route::getRoutes() as $route) {
            $uri        = $route->uri();
            $middleware = $route->gatherMiddleware();
            $mwFlat     = implode(',', $middleware);

            // Tenant routes should have tenant context
            if (str_starts_with($uri, 'tenant/') && !str_contains($mwFlat, 'tenant.access') && !str_contains($uri, 'login') && !str_contains($uri, 'register') && !str_contains($uri, 'invite') && !str_contains($uri, 'accept')) {
                $unguarded[] = $uri;
            }
        }

        if (empty($unguarded)) {
            $results[] = $this->pass('route.middleware.tenant_coverage', 'All /tenant/* routes appear protected', 'routes', 'high');
        } else {
            foreach (array_slice($unguarded, 0, 5) as $uri) {
                $results[] = $this->warning("route.middleware.unguarded", "Route may lack tenant.access middleware: {$uri}", 'routes', 'high');
            }
            if (count($unguarded) > 5) {
                $results[] = $this->warning('route.middleware.unguarded_more', count($unguarded) - 5 . ' more routes may lack tenant middleware', 'routes');
            }
        }

        return $results;
    }

    private function checkDuplicateRoutes(): array
    {
        $results = [];
        $seen    = [];
        $dupes   = [];

        foreach (Route::getRoutes() as $route) {
            $key = $route->methods()[0] . ':' . $route->uri();
            if (isset($seen[$key])) {
                $dupes[] = $key;
            }
            $seen[$key] = true;
        }

        if (empty($dupes)) {
            $results[] = $this->pass('route.duplicates', 'No duplicate routes detected', 'routes');
        } else {
            foreach ($dupes as $dupe) {
                $results[] = $this->warning('route.duplicate.' . md5($dupe), "Duplicate route: {$dupe}", 'routes', 'medium');
            }
        }

        return $results;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function pass(string $check, string $message, string $module = 'routes', string $severity = 'low'): array
    {
        return ['check' => $check, 'status' => 'pass', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => $severity];
    }

    private function fail(string $check, string $message, string $module = 'routes', string $severity = 'high'): array
    {
        return ['check' => $check, 'status' => 'fail', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => $severity];
    }

    private function warning(string $check, string $message, string $module = 'routes', string $severity = 'medium'): array
    {
        return ['check' => $check, 'status' => 'warning', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => $severity];
    }

    private function critical(string $check, string $message, string $module = 'routes', string $severity = 'critical'): array
    {
        return ['check' => $check, 'status' => 'critical', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => $severity];
    }
}
