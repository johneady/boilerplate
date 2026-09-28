<?php

use App\Audit\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('a deactivated account is refused at the login form, never signed in', function () {
    $user = User::factory()->deactivated()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['email' => 'This account has been deactivated.']);
    $this->assertGuest();
    expect(AuditLog::query()->ofEvent(AuditEvent::Login)->exists())->toBeFalse();
});

test('a verified passkey does not sign in a deactivated account', function (bool $deactivated) {
    $user = User::factory()->when($deactivated, fn ($factory) => $factory->deactivated())->create();
    $passkey = (new Passkey)->setRelation('user', $user);

    expect(Passkeys::allowsLogin(request(), $passkey))->toBe(! $deactivated);
})->with(['active' => false, 'deactivated' => true]);

test('a wrong password on a deactivated account gets the ordinary failure, not its status', function () {
    $user = User::factory()->deactivated()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
    $this->assertGuest();
});

test('signing in rehashes a password stored under older hashing settings', function () {
    $user = User::factory()->create();
    // Written past the model: its hashed cast refuses a hash made under
    // settings other than the current ones, which is the very case here.
    $staleHash = Hash::make('password', ['rounds' => 5]);
    DB::table('users')->where('id', $user->id)->update(['password' => $staleHash]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    expect($user->fresh()->getRawOriginal('password'))->not->toBe($staleHash);
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
