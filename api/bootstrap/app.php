<?php

declare(strict_types=1);

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // There is no sign-in page to send anyone to: this application serves
        // JSON only. Without this, an unauthenticated request that does not
        // happen to ask for JSON tries to redirect to a route named "login"
        // and dies with a 500 instead of saying plainly that credentials are
        // missing. A document fetched by the browser is exactly such a request.
        $middleware->redirectGuestsTo(static fn (): ?string => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The SPA is the only client, so every failure should arrive as JSON
        // rather than an HTML error page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
