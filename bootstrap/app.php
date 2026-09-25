<?php

use App\Http\Middleware\EnsureRegistrationIsEnabled;
use App\Http\Middleware\EnsureUserIsSubscribed;
use App\Http\Middleware\ThrottleSensitiveAuthRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Registered through `then` rather than the `api` argument, which
        // takes a single file and would fix the prefix at "api": versioning
        // is the point here, so each version is its own file under a prefix
        // carrying its version. Adding v2 is another group beside this one.
        then: function (): void {
            // 'throttle:api' is applied here rather than left to the `api`
            // group: since Laravel 11 the skeleton's api group contains only
            // SubstituteBindings, so a route registered under it is NOT rate
            // limited -- unlike Laravel 10 and earlier, where the group carried
            // a throttle and everyone inherited it. The limiter is defined in
            // AppServiceProvider. Applied at the group so a new endpoint is
            // covered by default rather than having to remember it.
            Route::middleware(['api', 'throttle:api'])
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(base_path('routes/api/v1.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Scopes itself to the register routes by name, so Fortify's route
        // file does not have to be republished to gate sign-up.
        $middleware->appendToGroup('web', EnsureRegistrationIsEnabled::class);

        // Same approach, same reason: registration and the two password-reset
        // POSTs are the Fortify routes that ship with no limiter and no config
        // key to add one.
        $middleware->appendToGroup('web', ThrottleSensitiveAuthRequests::class);

        // Route::middleware(['auth', 'subscribed:pro']) -- see User::subscribed().
        $middleware->alias(['subscribed' => EnsureUserIsSubscribed::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
