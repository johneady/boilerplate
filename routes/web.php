<?php

use App\Auth\DevLoginAccounts;
use App\Http\Controllers\DevLoginController;
use App\Http\Controllers\ErrorPagePreviewController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MailPreviewController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Livewire\Contact;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// The two public agent calculators. Plain views: the arithmetic runs in the
// browser (resources/js/calculators.js) from config/calculators.php, and
// nothing is submitted, so there is no controller or request to validate.
// Both slugs are in Page::RESERVED_SLUGS so no content page can shadow them.
Route::view('income-planner', 'calculators.income-planner')->name('calculators.income-planner');
Route::view('split-comparison', 'calculators.split-comparison')->name('calculators.split-comparison');

// Declared as its own route rather than served by the content-page catch-all
// below: it validates, persists and sends mail, so it is a Livewire component,
// and 'contact' is in Page::RESERVED_SLUGS so no page can shadow it.
Route::livewire('contact', Contact::class)->name('contact');

// Served by the application rather than as files in public/, so both follow
// the AllowSearchIndexing setting. public/robots.txt was deleted for this
// reason: a real file on disk is matched by the web server before the request
// reaches PHP, which would shadow this route.
Route::get('robots.txt', RobotsController::class)->name('robots');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

// The deep health check, beside rather than instead of the framework's '/up'
// (registered in bootstrap/app.php). /up proves the framework booted and is
// what the container HEALTHCHECK restarts on; this resolves the database,
// cache and queue-worker heartbeat for an uptime monitor. Keeping them apart
// is deliberate -- see config/health.php.
Route::get('health', HealthController::class)->name('health');

// Signed rather than merely authenticated: the signature bounds how long a
// link survives being shared, and MediaController still consults the owning
// record's policy on top of it. See App\Http\Controllers\MediaController.
Route::get('media/{media}', [MediaController::class, 'show'])
    ->middleware(['auth', 'signed'])
    ->name('media.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Registered only where quick dev logins are allowed, so the passwordless
// login route simply does not exist in production rather than depending on a
// runtime guard inside the controller to turn it away.
//
// The error-page preview is gated on the same switch and for the same reason:
// a 500 or a 503 cannot be reviewed by causing one (APP_DEBUG shows the Whoops
// trace, and `artisan down` takes the whole site with it), so these routes
// render the templates directly -- and must not exist on a deployed instance.
if (app(DevLoginAccounts::class)->enabled()) {
    Route::post('dev-login', DevLoginController::class)->name('dev-login');

    Route::get('dev/errors', ErrorPagePreviewController::class)->name('dev.errors');
    Route::get('dev/errors/{status}', ErrorPagePreviewController::class)
        ->whereNumber('status')
        ->name('dev.errors.show');
}

// The email previews name their safe environments rather than reusing the
// dev-login gate above: that gate is a denylist of production alone, so it is
// on for staging and any bespoke environment name. These render signed
// verification and reset URLs for a stand-in account, which is data-exposing
// behaviour -- and .ai/rules/dev-login.md is explicit that such behaviour is
// guarded by naming the safe environments, not by excluding production.
if (app()->environment(['local', 'testing'])) {
    Route::get('dev/mails', MailPreviewController::class)->name('dev.mails');
    Route::get('dev/mails/{slug}', MailPreviewController::class)->name('dev.mails.show');
}

require __DIR__.'/settings.php';

// A FALLBACK, not an ordinary catch-all, and that distinction is load-bearing.
//
// Laravel matches routes in registration order, so `Route::get('{page:slug}')`
// here would claim every single-segment path and shadow anything registered
// afterwards -- not only the settings routes required above, but any route a
// package, a test or a future edit adds later. Two tests in ErrorPagesTest
// register a route at runtime and got a 404 from exactly that.
//
// A fallback route is tried only once every other route has failed to match,
// whenever it was registered, so a page can never shadow a real route. The
// constraint still applies (a fallback with a parameter pattern that does not
// match simply 404s as normal), keeping this off paths with a dot or a slash in
// them, and Page::RESERVED_SLUGS stops an administrator creating a page whose
// slug a real route answers on -- which would save cleanly and then be quietly
// unreachable.
Route::fallback(PageController::class)
    ->where('fallbackPlaceholder', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('pages.show');
