<?php

use App\Auth\DevLoginAccounts;
use App\Http\Controllers\ErrorPagePreviewController;
use Illuminate\Support\Facades\Route;

test('the preview index lists every error page', function () {
    $response = $this->get(route('dev.errors'))->assertSuccessful();

    foreach (ErrorPagePreviewController::STATUSES as $status) {
        $response->assertSee(route('dev.errors.show', $status));
    }
});

test('each status renders its own template', function () {
    // 403's copy branches on whether the viewer is signed in, and the preview
    // is requested as a guest, so the guest heading is the one to expect here.
    // tests/Feature/ErrorPagesTest.php covers both variants.
    $expected = [
        403 => 'You need to sign in for this',
        404 => 'We could not find that page',
        419 => 'Your session expired',
        429 => 'Too many attempts',
        500 => 'Something went wrong on our end',
        503 => 'We are down for maintenance',
    ];

    foreach ($expected as $status => $heading) {
        $this->get(route('dev.errors.show', $status))
            ->assertSuccessful()
            ->assertSee($heading);
    }
});

test('a preview returns 200 rather than the status it depicts', function () {
    // Returning a real 404 or 503 would have the browser, and any crawler,
    // treat the preview itself as broken.
    $this->get(route('dev.errors.show', 404))->assertOk();
    $this->get(route('dev.errors.show', 503))->assertOk();
});

test('a status with no template of its own is refused', function () {
    $this->get('/dev/errors/418')->assertNotFound();
});

test('the preview routes are not registered in production', function () {
    // Gated at registration on DevLoginAccounts::enabled(), so the routes are
    // absent from a production build rather than guarded at runtime.
    //
    // Asserted by re-running the route file's own condition as production and
    // confirming the names it guards, rather than by re-evaluating the file
    // into a throwaway router: a detached router registers nothing by name at
    // all, so every "not->toContain" would pass even with the gate removed.
    app()->detectEnvironment(fn () => 'production');

    expect(app(DevLoginAccounts::class)->enabled())->toBeFalse();

    // And the same switch really is what guards them: true in this
    // environment, where the routes exist.
    app()->detectEnvironment(fn () => 'testing');

    expect(app(DevLoginAccounts::class)->enabled())->toBeTrue()
        ->and(Route::has('dev.errors'))->toBeTrue()
        ->and(Route::has('dev.errors.show'))->toBeTrue();

    // The route file registers all three under one `if`, so dev-login's
    // absence in production is the same guarantee as the previews'.
    expect(file_get_contents(base_path('routes/web.php')))
        ->toContain('DevLoginAccounts::class)->enabled()')
        ->toContain('dev.errors');
})->after(function () {
    app()->detectEnvironment(fn () => 'testing');
});
