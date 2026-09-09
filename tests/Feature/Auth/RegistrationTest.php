<?php

use App\Settings\SettingKey;
use App\Settings\Settings;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());

    // Public sign-up is off until an administrator enables it, so these tests
    // open it to exercise the registration flow itself. The gate's own
    // behavior is covered in tests/Feature/RegistrationSettingTest.php.
    app(Settings::class)->set(SettingKey::AllowRegistration, true);
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
