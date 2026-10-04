<?php

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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        foreach ([
            \App\Exceptions\VersionLockedException::class,
            \App\Exceptions\DomainRuleException::class,
            \App\Exceptions\InsufficientStockException::class,
        ] as $type) {
            $exceptions->render(function (\Throwable $e, Request $request) use ($type) {
                if ($e instanceof $type && ($request->is('api/*') || $request->expectsJson())) {
                    $status = $e instanceof \App\Exceptions\VersionLockedException ? 423 : 422;

                    return response()->json([
                        'message' => $e->getMessage(),
                        'error_type' => class_basename($type),
                    ], $status);
                }

                return null;
            });
        }
    })->create();
