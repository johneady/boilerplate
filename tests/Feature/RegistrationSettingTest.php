<?php

use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

/**
 * Turn public sign-up on or off the way an administrator would, then drop the
 * request-scoped cache so the next request reads the stored value.
 */
function allowRegistration(bool $allowed): void
{
    app(Settings::class)->set(SettingKey::AllowRegistration, $allowed);

    app()->forgetInstance(Settings::class);
}

test('the sign-up page is unavailable while registrations are closed', function () {
    allowRegistration(false);

    $this->get(route('register'))->assertNotFound();
});

test('a posted sign-up creates no account while registrations are closed', function () {
    allowRegistration(false);

    $response = $this->post(route('register.store'), [
        'name' => 'Uninvited',
        'email' => 'uninvited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    // The POST is closed as well as the page, so a visitor who kept the form
    // open, or crafted the request by hand, still cannot create an account.
    $response->assertNotFound();
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'uninvited@example.com']);
});

test('registrations are closed by default, before any setting is saved', function () {
    // No setting row is written here: the enum's default is what a freshly
    // installed application runs on.
    $this->get(route('register'))->assertNotFound();
});

test('the sign-up page is available once registrations are opened', function () {
    allowRegistration(true);

    $this->get(route('register'))->assertOk();
});

test('a new user can register once registrations are opened', function () {
    allowRegistration(true);

    $this->post(route('register.store'), [
        'name' => 'Invited',
        'email' => 'invited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'invited@example.com']);
});

test('the login page hides the sign-up link while registrations are closed', function () {
    allowRegistration(false);

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee(route('register'));
});

test('the login page offers the sign-up link once registrations are opened', function () {
    allowRegistration(true);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee(route('register'));
});

test('the home page hides the sign-up button while registrations are closed', function () {
    allowRegistration(false);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee(route('register'));
});

test('the home page offers the sign-up button once registrations are opened', function () {
    allowRegistration(true);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('register'));
});

test('closing registrations does not affect signed-in users', function () {
    allowRegistration(false);

    // The gate is on sign-up alone; an existing account must keep working.
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk();
});

test('an administrator can still create users while registrations are closed', function () {
    allowRegistration(false);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/users')
        ->assertSuccessful();
});
