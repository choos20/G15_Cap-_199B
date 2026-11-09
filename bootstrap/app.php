<?php

use App\Http\Middleware\EnsureCanManageTemplates;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\NoCacheMiddleware;
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
        $middleware->prepend(NoCacheMiddleware::class);

        // Runs on every web request, so no route can be reached while a
        // temporary password is still outstanding.
        $middleware->appendToGroup('web', ForcePasswordChange::class);

        $middleware->alias([
            'can.manage.templates' => EnsureCanManageTemplates::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
