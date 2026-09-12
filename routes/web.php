<?php

use App\Auth\DevLoginAccounts;
use App\Http\Controllers\DevLoginController;
use App\Http\Controllers\ErrorPagePreviewController;
use App\Http\Controllers\MailPreviewController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Served by the application rather than as files in public/, so both follow
// the AllowSearchIndexing setting. public/robots.txt was deleted for this
// reason: a real file on disk is matched by the web server before the request
// reaches PHP, which would shadow this route.
Route::get('robots.txt', RobotsController::class)->name('robots');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

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
