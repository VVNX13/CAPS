<?php

use App\Support\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Payment webhooks are called by external services that cannot send a
        // CSRF token; they are authenticated with a shared secret instead.
        $middleware->validateCsrfTokens(except: [
            'api/webhook/*',
        ]);

        // Send unauthenticated visitors of the owner area back to the login form.
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Shop-wide configuration (dynamic QRIS, QR stands, gateway credentials,
        // webhook secret) is admin only.
        $middleware->alias([
            'admin' => \App\Http\Middleware\RequireAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Keep the JSON API response shape consistent for validation failures.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                $firstMessage = collect($e->errors())->flatten()->first();

                return ApiResponse::error($firstMessage ?: $e->getMessage(), $e->status, $e->errors());
            }
        });
    })->create();
