<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: ['api/webhooks/paymongo', 'api/webhooks/*']);
        $middleware->prepend(HandleCors::class);
        $middleware->statefulApi();
        $middleware->alias([
            'feature.access'     => \App\Http\Middleware\FeatureAccessMiddleware::class,
            'tenant.access'      => \App\Http\Middleware\EnsureTenantAccess::class,
            'reseller.access'    => \App\Http\Middleware\EnsureResellerAccess::class,
            'reseller.active'    => \App\Http\Middleware\EnsureResellerActive::class,
            'partner.access'     => \App\Http\Middleware\EnsurePartnerAccess::class,
            'password.confirm'   => \Illuminate\Auth\Middleware\RequirePassword::class,
            'tenant.subdomain'   => \App\Http\Middleware\DetectTenantSubdomain::class,
            'api.tenant'         => \App\Http\Middleware\SetApiTenantContext::class,
            'legal.agreements'   => \App\Http\Middleware\EnsureLegalAgreementsAccepted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            $path    = $request->path();
            $message = 'Your session has expired. Please sign in to continue.';
            session()->put('url.intended', $request->url());

            if ($path === 'reseller' || str_starts_with($path, 'reseller/')) {
                return redirect()->route('reseller.login')->with('status', $message);
            }
            if ($path === 'partner' || str_starts_with($path, 'partner/')) {
                return redirect()->route('partner.login')->with('status', $message);
            }
            if ($path === 'tenant' || str_starts_with($path, 'tenant/')) {
                return redirect()->route('tenant.login')->with('status', $message);
            }
            return redirect()->route('login')->with('status', $message);
        });
    })->create();
