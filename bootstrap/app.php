<?php

use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureSessionIsNotIdle;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'session.idle' => EnsureSessionIsNotIdle::class,
            'password.changed' => EnsurePasswordIsChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
