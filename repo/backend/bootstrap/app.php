<?php

use App\Exceptions\Handler;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\EnsureTokenNotExpired;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\ValidateExportAccess;
use App\Http\Middleware\ValidateMediaAccess;
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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'token.not_expired' => EnsureTokenNotExpired::class,
            'media.access' => ValidateMediaAccess::class,
            'export.access' => ValidateExportAccess::class,
            'idempotency' => IdempotencyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return app(Handler::class)->render($request, $exception);
        });
    })->create();
