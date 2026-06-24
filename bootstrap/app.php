<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies when behind a load balancer (required for HTTPS detection,
        // correct scheme in URL::forceScheme('https'), and proper client IP logging).
        // In production, restrict to your LB's IP range via TrustProxy middleware config.
        $middleware->trustProxies(at: '*');

        // Global middleware — applied to every request
        $middleware->append([
            // Throttle API/login endpoints to mitigate brute-force and DoS
            // (already configured via RouteServiceProvider for web routes)
        ]);

        // Route middleware aliases — usable via ->middleware('alias') in routes
        $middleware->alias([
            'role'       => \App\Http\Middleware\RoleMiddleware::class,
            'idempotent' => \App\Http\Middleware\IdempotencyMiddleware::class,
        ]);

        // Exclude these URIs from CSRF protection (webhooks, callback URLs).
        // Keep this list SHORT — every entry is a potential CSRF hole.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // TokenMismatchException: session expired → send user back to login
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            return redirect()->route('login')->withErrors(['email' => 'Sesi telah berakhir. Silakan login kembali.']);
        });

        // DomainException: business rule violation → 422 with message
        $exceptions->render(function (\App\Exceptions\DomainException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'domain_violation',
                    'message' => $e->getMessage(),
                ], 422);
            }
        });

        // ModelNotFoundException: 404 for missing resources (JSON only — Blade redirects handled elsewhere)
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'resource_not_found',
                    'message' => 'The requested resource was not found.',
                ], 404);
            }
        });

        // ThrottleRequestsException: 429 with Retry-After
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'rate_limited',
                    'message' => 'Too many requests. Please slow down.',
                ], 429, ['Retry-After' => '60']);
            }
        });
    })
    ->create();
