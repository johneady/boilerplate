<?php

use App\Models\User;
use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Support\Facades\Route;

/*
 * The `errors::` namespace is registered lazily -- Laravel's exception handler
 * calls RegisterErrorViewPaths only when it is about to render an error view,
 * so the hint does not exist on a normal request. Registering it here is what
 * the handler itself does, and it is what puts resources/views/errors ahead of
 * the framework's bundled templates in the namespace's path list.
 */
beforeEach(function () {
    (new RegisterErrorViewPaths)();
});

/**
 * Render an error view the way the exception handler resolves it: through the
 * `errors::{code}` namespace rather than the `errors.{code}` path, so a
 * template the handler would not actually pick up is caught.
 */
function renderErrorPage(int $status): string
{
    return view('errors::'.$status)->render();
}

test('this application supplies its own template for each status code', function () {
    // view()->exists() alone is a false pass: the framework bundles its own
    // 403/404/419/429/500/503 views in the same namespace, so the assertion
    // that matters is which file the namespace resolves to.
    foreach ([403, 404, 419, 429, 500, 503] as $status) {
        expect(view()->exists('errors::'.$status))->toBeTrue()
            ->and(view('errors::'.$status)->getPath())
            ->toBe(resource_path('views/errors/'.$status.'.blade.php'));
    }
});

test('a missing page renders the custom 404', function () {
    $response = $this->get('/no-such-page-exists');

    $response->assertNotFound()
        ->assertSee('We could not find that page')
        ->assertSee(config('app.name'));
});

test('a forbidden route renders the custom 403', function () {
    Route::get('/test-forbidden', fn () => abort(403))->middleware('web');

    $this->actingAs(User::factory()->create())
        ->get('/test-forbidden')
        ->assertForbidden()
        ->assertSee('You do not have access to this');
});

test('a non-admin hitting the admin panel gets the styled 403', function () {
    // The panel aborts 403 through Filament's own middleware, so this proves
    // the template is reached on a real application path rather than only on
    // a synthetic abort().
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden()
        ->assertSee('You do not have access to this');
});

test('the 419 page explains that the session expired', function () {
    // The default "Page Expired" is what users actually hit after leaving a
    // form open, and it reads as a fault rather than as "log in and retry".
    expect(renderErrorPage(419))
        ->toContain('Your session expired')
        ->toContain('Nothing was saved');
});

test('every error page is marked noindex', function () {
    foreach ([403, 404, 419, 429, 500, 503] as $status) {
        expect(renderErrorPage($status))->toContain('name="robots" content="noindex, nofollow"');
    }
});

test('every error page states its own status code', function () {
    foreach ([403, 404, 419, 429, 500, 503] as $status) {
        expect(renderErrorPage($status))->toContain((string) $status);
    }
});

test('error pages do not depend on the database', function () {
    // A 500 is most often a database outage, and the app layout's
    // View::composer('*') reads the settings table. An error page that
    // inherits it throws while rendering, and the user gets Laravel's
    // unstyled fallback at exactly the moment these templates exist for.
    //
    // The outage is simulated by pointing the DEFAULT at a throwaway
    // connection that cannot connect, rather than by breaking the one the
    // suite is using: RefreshDatabase holds a transaction on that, and
    // purging it destroys the shared in-memory database for later tests.
    $default = config('database.default');

    config([
        'database.connections.unreachable_test' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'nothing_here',
            'username' => 'nobody',
            'password' => '',
        ],
        'database.default' => 'unreachable_test',
    ]);

    // Restored before the test ends: RefreshDatabase resolves the connection
    // it rolls back at teardown from database.default, so leaving the bogus
    // one in place makes it roll back the wrong connection and every later
    // test in the process fails with "cannot start a transaction within a
    // transaction".
    $restore = fn () => config(['database.default' => $default]);

    // Drop the settings instance resolved by earlier reads in this request,
    // so the composer genuinely re-queries against the broken connection.
    app()->forgetScopedInstances();

    try {
        foreach ([403, 404, 419, 429, 500, 503] as $status) {
            expect(renderErrorPage($status))->toContain(config('app.name'));
        }
    } finally {
        $restore();
        app()->forgetScopedInstances();
    }
});

test('error pages are branded from config rather than the settings table', function () {
    // The documented exception to the "views read $businessName" rule: the
    // brand has to survive the database being down.
    expect(renderErrorPage(500))->toContain(config('app.name'));
});
