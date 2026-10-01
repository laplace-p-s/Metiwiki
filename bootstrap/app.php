<?php

use App\Http\Middleware\EnsureAuthFeatureIsEnabled;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PrepareInstallation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/-/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 初期設定の前は APP_KEY の生成と DB の準備が要るため、セッションより前で動かす
        $middleware->prepend(PrepareInstallation::class);

        $middleware->encryptCookies(except: ['appearance']);

        $middleware->web(append: [
            EnsureAuthFeatureIsEnabled::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
