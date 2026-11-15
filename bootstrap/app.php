<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveCurrentTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'current_tenant' => ResolveCurrentTenant::class,
        ]);

        // Route model binding runs through the tenant-scoped models, so the current
        // tenant must be known before it: a dashboard of another organization is then
        // simply "not found" (404) instead of "found, but forbidden" (403).
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveCurrentTenant::class);

        $middleware->web(append: [
            SecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
