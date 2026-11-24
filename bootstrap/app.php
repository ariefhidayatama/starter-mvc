<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureApiAccess;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RememberTableQuery;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Halaman daftar admin: filter disimpan di session, URL tetap bersih.
        $middleware->alias([
            'remember.table' => RememberTableQuery::class,
            // RBAC API lapis 1: role harus punya permission "api.access".
            'api.access' => EnsureApiAccess::class,
        ]);

        // Group "api": dipasang paling depan agar seluruh proses setelahnya
        // (auth, validasi, exception) tahu bahwa klien meminta JSON.
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        // Pasang rate limiter "api" (didefinisikan di AppServiceProvider)
        // ke seluruh route API.
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Semua error di /api/* → format JSON standar (lihat ApiExceptionRenderer).
        // Route web tidak terpengaruh (renderer mengembalikan null).
        $exceptions->render(
            fn (Throwable $e, Request $request) => app(ApiExceptionRenderer::class)($e, $request),
        );
    })->create();
