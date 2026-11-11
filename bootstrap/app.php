<?php

use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Webhook Telegram tanpa session/CSRF (hindari interferensi middleware web)
            Route::middleware([TrustProxies::class])
                ->group(base_path('routes/telegram.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('web', TrustProxies::class);
        $middleware->prependToGroup('api', TrustProxies::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

