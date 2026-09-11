<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('logging out returns to the home page', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

/**
 * The dashboard is the ORDINARY user's landing page, not an admin screen, so it
 * links only to things any authenticated user may do to their own account.
 */
test('the dashboard greets the user and links to their own settings', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Welcome back, Ada Lovelace')
        ->assertSee(route('profile.edit'))
        ->assertSee(route('security.edit'))
        ->assertSee(route('appearance.edit'));
});

/**
 * These shipped with the Livewire starter kit and point at Laravel's own repo
 * and docs. They are not this application's, and users are not its developers.
 */
test('the starter kit repository and documentation links are gone', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('github.com/laravel/livewire-starter-kit')
        ->assertDontSee('laravel.com/docs/starter-kits');
});
