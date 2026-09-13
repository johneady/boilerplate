<?php

use App\Models\User;
use Laravel\Fortify\Features;

/*
 * Browser coverage for the authentication paths whose behaviour depends on
 * JavaScript actually running -- the Livewire form round-trip and the Flux
 * components the auth pages are built from. The feature suite already asserts
 * the server-side outcomes; what it cannot see is a page that renders but
 * throws in the browser, which is how these break on a dependency bump.
 */

test('a user can log in through the browser', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password',
    ]);

    $page = visit('/login');

    $page->assertSee('Log in to your account')
        ->fill('email', 'ada@example.com')
        ->fill('password', 'password')
        ->click('@login-button')
        // Path rather than URL: the test server binds an ephemeral port, so
        // the host in any absolute URL differs from run to run.
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();

    $this->assertAuthenticated();
});

test('an invalid password keeps the user on the login page with an error', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password',
    ]);

    $page = visit('/login');

    $page->fill('email', 'ada@example.com')
        ->fill('password', 'wrong-password')
        ->click('@login-button')
        ->assertSee('These credentials do not match our records.')
        ->assertNoJavaScriptErrors();

    $this->assertGuest();
});

/**
 * The challenge screen is the one auth page a passing feature test cannot
 * fully vouch for: it switches between the authentication-code and
 * recovery-code panels in the browser, so a broken build leaves a user who
 * has 2FA enabled unable to finish logging in at all.
 */
test('a user with two-factor enabled reaches the challenge screen', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password',
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code-one'])),
        'two_factor_confirmed_at' => now(),
    ]);

    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue();

    $page = visit('/login');

    $page->fill('email', 'ada@example.com')
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertPathIs('/two-factor-challenge')
        ->assertSee('Authentication code')
        ->assertNoJavaScriptErrors();

    $this->assertGuest();
})->skip(
    fn (): bool => ! Features::enabled(Features::twoFactorAuthentication()),
    'Two-factor authentication is disabled.',
);
