<?php

namespace App\Services\Stitch;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Snapshot of the application's registered routes, used by STITCH commands
 * to validate route names and inspect middleware without re-querying the
 * router for every check.
 */
class RouteRegistry
{
    /** @var array<int, array{name: ?string, uri: string, methods: array<int, string>, action: string, middleware: array<int, string>}> */
    protected array $routes;

    public function __construct()
    {
        $this->routes = collect(RouteFacade::getRoutes())
            ->map(fn (Route $route) => [
                'name' => $route->getName(),
                'uri' => $route->uri() === '/' ? '/' : '/'.trim($route->uri(), '/'),
                'methods' => $route->methods(),
                'action' => $route->getActionName(),
                'middleware' => array_values($route->gatherMiddleware()),
            ])
            ->values()
            ->all();
    }

    public function has(string $name): bool
    {
        return RouteFacade::has($name);
    }

    /** @return array<int, array{name: ?string, uri: string, methods: array<int, string>, action: string, middleware: array<int, string>}> */
    public function all(): array
    {
        return $this->routes;
    }

    /** @return array<int, string> */
    public function names(): array
    {
        return collect($this->routes)->pluck('name')->filter()->unique()->values()->all();
    }

    /** @return array{name: ?string, uri: string, methods: array<int, string>, action: string, middleware: array<int, string>}|null */
    public function findByName(string $name): ?array
    {
        return collect($this->routes)->firstWhere('name', $name);
    }

    /**
     * Whether a route definition carries an `auth*` guard middleware.
     */
    public function requiresAuth(array $route): bool
    {
        foreach ($route['middleware'] as $middleware) {
            if (str_starts_with($middleware, 'auth')) {
                return true;
            }
        }

        return false;
    }
}
