<?php

use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

test('the password policy is enforced outside local and testing environments', function () {
    Http::fake();

    // Staging and any other non-local environment must inherit the strict
    // policy, not only production. Leaving the testing environment also
    // re-enables CSRF verification, so the request carries the session token.
    $this->app->detectEnvironment(fn () => 'staging');

    $token = Str::random(40);

    $this->withSession(['_token' => $token])->post(route('register.store'), [
        '_token' => $token,
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
});
