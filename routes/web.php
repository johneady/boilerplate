<?php

use App\Auth\DevLoginAccounts;
use App\Http\Controllers\DevLoginController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Registered only where quick dev logins are allowed, so the passwordless
// login route simply does not exist in production rather than depending on a
// runtime guard inside the controller to turn it away.
if (app(DevLoginAccounts::class)->enabled()) {
    Route::post('dev-login', DevLoginController::class)->name('dev-login');
}

require __DIR__.'/settings.php';
