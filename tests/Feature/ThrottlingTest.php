<?php

use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

/*
 * Rate limits on the endpoints Fortify ships WITHOUT one.
 *
 * Login, the two-factor challenge, passkeys and email verification are
 * throttled by Fortify itself through config('fortify.limiters'); registration
 * and both halves of the password reset are not, and are covered here along
 * with the API group, whose `api` middleware group has carried no throttle
 * since Laravel 11.
 *
 * What each test asserts is that the limit EXISTS and that it is keyed on the
 * right thing. The keying is the half that silently breaks: a per-address limit
 * that an attacker sidesteps by changing capitalisation, or a per-IP limit on an
 * endpoint where the IP is not what varies, still returns 429 in a naive test
 * while protecting nothing.
 */

beforeEach(function (): void {
    // Every test here shares 127.0.0.1 and several deliberately exhaust their
    // limiter. CACHE_STORE is `array` under phpunit.xml, so each test starts
    // with empty buckets and no clearing between them is needed -- but a change
    // to a persistent store would make these tests interfere, which is worth
    // knowing before one is made.
    Notification::fake();
});

test('the api group is rate limited', function () {
    // The regression this pins: Laravel 11 emptied the `api` middleware group
    // of its throttle, so a route registered under it is unlimited unless the
    // application puts one back. Asserting the header rather than driving 60
    // requests keeps the test fast and states the limit it expects.
    $this->getJson(route('api.v1.ping'))
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', 60);
});

test('the api rate limit returns 429 once it is exhausted', function () {
    // Driven through real requests rather than by seeding the limiter bucket:
    // ThrottleRequests hashes its cache key as md5($limiterName.$key), so a
    // test that writes the key itself asserts against a framework internal and
    // passes whether or not the middleware is actually attached.
    foreach (range(1, 60) as $attempt) {
        $this->getJson(route('api.v1.ping'))->assertOk();
    }

    $this->getJson(route('api.v1.ping'))->assertStatus(429);
});

test('registration is rate limited by ip', function () {
    app(Settings::class)->set(SettingKey::AllowRegistration, true);

    // Ten sign-up attempts an hour from one address are allowed; the eleventh
    // is refused.
    //
    // The attempts deliberately FAIL validation (mismatched confirmation), so
    // the session stays a guest throughout. A successful registration logs the
    // user straight in -- Fortify's RegisteredUserController calls
    // guard->login() -- and every later request would then be turned away by
    // the `guest` middleware before the limiter counted it. Failed attempts are
    // also the realistic shape of the attack this limit exists to bound: an
    // unbounded sign-up endpoint is a way to fill the users table and to make
    // the application send mail on demand.
    foreach (range(1, 10) as $attempt) {
        $this->post(route('register.store'), [
            'name' => 'Ada Lovelace',
            'email' => "ada{$attempt}@example.test",
            'password' => 'password',
            'password_confirmation' => 'does-not-match',
        ])->assertStatus(302);
    }

    expect(auth()->check())->toBeFalse();

    $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada-final@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(429);

    // The point of the limit: no account was created by the refused request.
    expect(User::where('email', 'ada-final@example.test')->exists())->toBeFalse();
})->skip(fn (): bool => ! Features::enabled(Features::registration()), 'Registration is disabled.');

test('the password reset link endpoint is rate limited per address', function () {
    $user = User::factory()->create(['email' => 'ada@example.test']);

    // Five requests are allowed per address per hour; the sixth is refused.
    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertStatus(302);
    }

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertStatus(429);
})->skip(fn (): bool => ! Features::enabled(Features::resetPasswords()), 'Password resets are disabled.');

test('the password reset link limit is not bypassed by changing capitalisation', function () {
    $user = User::factory()->create(['email' => 'ada@example.test']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.email'), ['email' => $user->email])->assertStatus(302);
    }

    // Without the Str::lower() normalisation in the limiter key, this address
    // hashes to a different bucket and gets a fresh allowance -- which would
    // make the per-address limit above decorative.
    $this->post(route('password.email'), ['email' => 'ADA@example.test'])
        ->assertStatus(429);
})->skip(fn (): bool => ! Features::enabled(Features::resetPasswords()), 'Password resets are disabled.');

test('the password reset link endpoint is also rate limited per ip across addresses', function () {
    // Enumeration: one client walking a list of addresses stays under the
    // per-address limit on every one of them, so only the per-IP limit stops it.
    foreach (range(1, 15) as $attempt) {
        $this->post(route('password.email'), ['email' => "user{$attempt}@example.test"])
            ->assertStatus(302);
    }

    $this->post(route('password.email'), ['email' => 'someone-else@example.test'])
        ->assertStatus(429);
})->skip(fn (): bool => ! Features::enabled(Features::resetPasswords()), 'Password resets are disabled.');

test('the password reset submission is rate limited', function () {
    $user = User::factory()->create(['email' => 'ada@example.test']);

    // The token is the secret here, so the limit is keyed on the IP: a guesser
    // varies the token, not the address. Ten guesses an hour are allowed.
    $guess = fn (int $attempt) => $this->post(route('password.update'), [
        'token' => "an-invalid-token-{$attempt}",
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    foreach (range(1, 10) as $attempt) {
        $guess($attempt)->assertStatus(302);
    }

    $guess(11)->assertStatus(429);
})->skip(fn (): bool => ! Features::enabled(Features::resetPasswords()), 'Password resets are disabled.');

test('an authenticated request does not consume the guest rate limit', function () {
    // This middleware runs from the `web` group, ahead of the route-level
    // `guest` middleware Fortify puts on these routes, so a signed-in user's
    // POST is turned away by RedirectIfAuthenticated without reaching the
    // controller -- no mail, no account, no token guess. Counting it anyway
    // would let a signed-in user exhaust an IP-keyed bucket that guests share,
    // locking real visitors on the same office or carrier NAT out of password
    // resets. This is the regression test for that: it failed with 429 before
    // the authenticated short-circuit was added.
    $signedIn = User::factory()->create(['email' => 'signed-in@example.test']);
    $guest = User::factory()->create(['email' => 'ada@example.test']);

    // Well past the 15/hour per-IP allowance on this endpoint.
    foreach (range(1, 20) as $attempt) {
        $this->actingAs($signedIn)
            ->post(route('password.email'), ['email' => "someone{$attempt}@example.test"])
            ->assertStatus(302);
    }

    auth()->logout();

    // The guest's allowance must be untouched.
    $this->post(route('password.email'), ['email' => $guest->email])
        ->assertStatus(302);
})->skip(fn (): bool => ! Features::enabled(Features::resetPasswords()), 'Password resets are disabled.');

test('the throttling middleware leaves other routes alone', function () {
    // The middleware is on the whole web group and self-scopes by route name,
    // so this pins that it does not start throttling unrelated pages.
    foreach (range(1, 12) as $attempt) {
        $this->get('/')->assertOk();
    }
});
