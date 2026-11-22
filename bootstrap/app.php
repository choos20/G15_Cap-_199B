<?php

use App\Http\Middleware\EnsureCanManageTemplates;
use App\Http\Middleware\EnsureMfaVerified;
use App\Http\Middleware\ExpireInactiveSession;
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
        // DigitalOcean terminates the public request at its proxy and sends
        // the client address in X-Forwarded-For. Trust only the immediate
        // caller so Request::ip() can identify the client for audit records.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', 'REMOTE_ADDR'));

        $middleware->prepend(NoCacheMiddleware::class);

        // Runs on every web request, so no route can be reached while a
        // temporary password is still outstanding.
        $middleware->appendToGroup('web', ExpireInactiveSession::class);
        $middleware->appendToGroup('web', ForcePasswordChange::class);

        // High-privilege users must complete TOTP MFA before entering the
        // authenticated application. Personal security pages remain available
        // so they can see their MFA status while enrollment is pending.
        $middleware->appendToGroup('web', EnsureMfaVerified::class);

        $middleware->alias([
            'can.manage.templates' => EnsureCanManageTemplates::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
