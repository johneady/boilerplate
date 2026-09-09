<?php

use App\Models\User;
use Filament\Facades\Filament;
use Spatie\LoginLink\Exceptions\NotAllowedInCurrentEnvironment;

test('the dev login links are shown on the login page locally', function () {
    config(['login-link.allowed_environments' => ['local', 'testing']]);

    User::factory()->admin()->create(['email' => config('first.user.email')]);

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Quick dev login')
        ->assertSee(config('first.user.email'));
});

test('the badge promises the admin panel only for a real admin', function () {
    config(['login-link.allowed_environments' => ['local', 'testing']]);

    User::factory()->admin()->create(['email' => config('first.user.email')]);

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Admin panel');
});

test('a first user that was never promoted is not badged as an admin', function () {
    config(['login-link.allowed_environments' => ['local', 'testing']]);

    User::factory()->create(['email' => config('first.user.email')]);

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertDontSee('Admin panel');
});

test('an account that has not been seeded yet is badged as missing', function () {
    config(['login-link.allowed_environments' => ['local', 'testing']]);

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Not seeded')
        ->assertDontSee('Admin panel');
});

test('the badged name comes from the seeded account, not the config default', function () {
    config(['login-link.allowed_environments' => ['local', 'testing']]);

    User::factory()->admin()->create([
        'email' => config('first.user.email'),
        'name' => 'Abigail Mills V',
    ]);

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Abigail Mills V');
});

test('the dev login links are hidden outside the allowed environments', function () {
    config(['login-link.allowed_environments' => ['local']]);
    app()->detectEnvironment(fn () => 'production');

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertDontSee('Quick dev login');
});

test('a login link signs in an admin and lands on the admin panel', function () {
    config(['login-link.allowed_environments' => ['local', 'testing']]);

    $admin = User::factory()->admin()->create();

    $this->post(route('loginLinkLogin'), ['email' => $admin->email])
        ->assertRedirect(Filament::getPanel('admin')->getUrl());

    $this->assertAuthenticatedAs($admin);
});

test('a login link signs in a non-admin and lands on the dashboard', function () {
    config(['login-link.allowed_environments' => ['local', 'testing']]);

    $user = User::factory()->create();

    $this->post(route('loginLinkLogin'), ['email' => $user->email])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('login links are rejected outside the allowed environments', function () {
    $user = User::factory()->create();

    $this->withoutExceptionHandling()
        ->post(route('loginLinkLogin'), ['email' => $user->email]);
})->throws(NotAllowedInCurrentEnvironment::class);
