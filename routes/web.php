<?php

use App\Auth\DevLoginAccounts;
use App\Http\Controllers\DevLoginController;
use App\Http\Controllers\ErrorPagePreviewController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

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

require __DIR__.'/settings.php';
