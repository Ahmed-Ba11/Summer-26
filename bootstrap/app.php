<?php

use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');

    $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'ui_locale', 'font_scale']);

    $middleware->web(append: [
        HandleAppearance::class,
        EnsureOnboarded::class,
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
    ]);
})
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($exception instanceof ValidationException && $request->header('X-Inertia')) {
                return redirect()->back()->withErrors($exception->errors());
            }

            return $response;
        });
    })->create();
