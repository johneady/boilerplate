<?php

use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;

test('the home page renders', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee(config('app.name'))
        ->assertSee('We make the thing that holds the other things.');
});

test('the home page shows the configured business name throughout', function () {
    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');

    $this->get('/')
        ->assertSuccessful()
        // The header brand, the <title>, the body copy and the footer all read
        // from the one setting.
        ->assertSee('Cromulent Widgets')
        ->assertSee('Since the beginning, Cromulent Widgets has specialised')
        ->assertSee('Cromulent Widgets — a division of nothing in particular.', escape: false)
        ->assertDontSee('Boilerplate Industries');
});

test('the home page falls back to the app name when no business name is stored', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee(config('app.name'));
});

test('the home page offers login and register to guests', function () {
    // Sign-up is off by default, so it is turned on here to assert the link
    // the page shows when registrations are open. The closed case lives in
    // tests/Feature/RegistrationSettingTest.php.
    app(Settings::class)->set(SettingKey::AllowRegistration, true);

    $this->get('/')
        ->assertSee(route('login'))
        ->assertSee(route('register'));
});

test('the home page links non-admins to the dashboard, not the panel', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertSee(route('dashboard'))
        ->assertDontSee('Admin');
});

test('the home page links admins to the panel, not the dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/')
        ->assertSee('Admin')
        ->assertDontSee(route('dashboard'));
});

test('the auth pages use the split layout with the backdrop image', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('images/auth/backdrop.svg')
        ->assertSee('Built for the work that comes next.');
});
