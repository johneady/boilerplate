<?php

use App\Http\Middleware\EnsureRegistrationIsEnabled;
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
            Route::middleware('api')
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(base_path('routes/api/v1.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Scopes itself to the register routes by name, so Fortify's route
        // file does not have to be republished to gate sign-up.
        $middleware->appendToGroup('web', EnsureRegistrationIsEnabled::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
