<?php

use App\Auth\DevLoginAccounts;
use App\Models\User;

afterEach(function () {
    // detectEnvironment() mutates the container-wide environment, which would
    // otherwise leak into every test that runs after this file's gate tests.
    app()->detectEnvironment(fn () => 'testing');
});
use Filament\Facades\Filament;

test('the dev login links are shown on the login page locally', function () {
    User::factory()->admin()->create(['email' => config('first.user.email')]);

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Quick dev login')
        ->assertSee(config('first.user.email'));
});

test('the badge promises the admin panel only for a real admin', function () {
    User::factory()->admin()->create(['email' => config('first.user.email')]);

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Admin panel');
});

test('a first user that was never promoted is not badged as an admin', function () {
    User::factory()->create(['email' => config('first.user.email')]);

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertDontSee('Admin panel');
});

test('an account that has not been seeded yet is badged as missing', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Not seeded')
        ->assertDontSee('Admin panel');
});

test('the badged name comes from the seeded account, not the config default', function () {
    User::factory()->admin()->create([
        'email' => config('first.user.email'),
        'name' => 'Abigail Mills V',
    ]);

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Abigail Mills V');
});

test('a dev login signs in an admin and lands on the admin panel', function () {
    $admin = User::factory()->admin()->create(['email' => config('first.user.email')]);

    $this->post(route('dev-login'), ['account' => 0])
        ->assertRedirect(Filament::getPanel('admin')->getUrl());

    $this->assertAuthenticatedAs($admin);
});

test('a dev login signs in a non-admin and lands on the dashboard', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $this->post(route('dev-login'), ['account' => 1])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('a dev login continues to the url the visitor was headed for', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $this->withSession(['url.intended' => url('/settings/profile')])
        ->post(route('dev-login'), ['account' => 1])
        ->assertRedirect(url('/settings/profile'));
});

test('a position outside the configured accounts is not found', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $this->post(route('dev-login'), ['account' => 99])
        ->assertNotFound();

    $this->assertGuest();
});

test('a position naming an account that has not been seeded is not found', function () {
    $this->post(route('dev-login'), ['account' => 0])
        ->assertNotFound();

    $this->assertGuest();
});

test('the account position is required', function () {
    $this->post(route('dev-login'), [])
        ->assertSessionHasErrors('account');

    $this->assertGuest();
});

test('an email in the request cannot select an account that was never offered', function () {
    User::factory()->admin()->create(['email' => config('first.user.email')]);
    $outsider = User::factory()->create(['email' => 'outsider@example.com']);

    $this->post(route('dev-login'), ['account' => 0, 'email' => $outsider->email])
        ->assertRedirect(Filament::getPanel('admin')->getUrl());

    $this->assertAuthenticatedAs(User::where('email', config('first.user.email'))->sole());
});

test('a redirect url in the request is ignored', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $this->post(route('dev-login'), [
        'account' => 1,
        'redirect_url' => 'https://evil.example.com/steal',
    ])->assertRedirect('/dashboard');
});

test('dev logins are disabled outside the allowed environments', function () {
    app()->detectEnvironment(fn () => 'production');

    // routes/web.php only registers the dev-login route when this reports
    // true, so a false here is what keeps the route out of production.
    expect(app(DevLoginAccounts::class)->enabled())->toBeFalse();
});

/**
 * The gate is a denylist: production is the ONLY environment that withholds the
 * quick logins, so a bespoke environment name works without being registered.
 *
 * This is a deliberate trade -- a deployed instance that is not 'production'
 * offers passwordless login to the seeded accounts.
 */
test('dev logins are enabled in every environment except production', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);

    expect(app(DevLoginAccounts::class)->enabled())->toBeTrue();
})->with(['local', 'testing', 'staging', 'demo', 'review-42']);

/**
 * A blocked list that resolved to nothing would turn passwordless login ON in
 * production, so the gate falls back to blocking production regardless.
 */
test('the gate fails closed in production when the blocked list is empty', function (mixed $blocked) {
    app()->detectEnvironment(fn () => 'production');

    config(['dev-login.blocked_environments' => $blocked]);

    expect(app(DevLoginAccounts::class)->enabled())->toBeFalse();
})->with([
    'empty array' => [[]],
    'blank strings' => [['', '   ']],
    'null' => [null],
]);

test('an unusable account does not renumber the accounts after it', function () {
    // The position is what a submitted form names, so dropping the first entry
    // must not shift the second one into its place -- a stale form would
    // otherwise log in as a different user than the one it was rendered for.
    config(['dev-login.accounts' => [
        ['email' => '', 'name' => 'Missing'],
        ['email' => 'test@example.com', 'name' => 'Test User'],
    ]]);

    $user = User::factory()->create(['email' => 'test@example.com']);

    expect(app(DevLoginAccounts::class)->all())->toHaveCount(1)
        ->and(app(DevLoginAccounts::class)->all()[0]['index'])->toBe(1);

    $this->post(route('dev-login'), ['account' => 1])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('the position vacated by an unusable account logs nobody in', function () {
    config(['dev-login.accounts' => [
        ['email' => '', 'name' => 'Missing'],
        ['email' => 'test@example.com', 'name' => 'Test User'],
    ]]);

    User::factory()->create(['email' => 'test@example.com']);

    $this->post(route('dev-login'), ['account' => 0])->assertNotFound();

    $this->assertGuest();
});

test('an account with a blank name is labelled with its email', function () {
    config(['dev-login.accounts' => [
        ['email' => 'test@example.com', 'name' => ''],
    ]]);

    // No seeded user, so the label can only come from the config fallback.
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('test@example.com');
});
