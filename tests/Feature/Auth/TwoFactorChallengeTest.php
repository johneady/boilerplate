<?php

use App\Models\User;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function () {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));
});

test('two factor challenge can be completed with a valid authentication code', function () {
    $secret = app(Google2FA::class)->generateSecretKey();

    $user = User::factory()->withTwoFactor()->create();
    $user->forceFill(['two_factor_secret' => encrypt($secret)])->save();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login.store'), [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('two factor challenge cannot be completed with an invalid authentication code', function () {
    // The verifier needs a full-length secret; the factory's placeholder is
    // too short and would throw instead of failing validation.
    $user = User::factory()->withTwoFactor()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt(app(Google2FA::class)->generateSecretKey()),
    ])->save();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));

    $unrelatedCode = app(Google2FA::class)
        ->getCurrentOtp(app(Google2FA::class)->generateSecretKey());

    $this->from(route('two-factor.login'))
        ->post(route('two-factor.login.store'), ['code' => $unrelatedCode])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('two factor challenge can be completed with a recovery code that is then consumed', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->forceFill([
        'two_factor_recovery_codes' => encrypt(json_encode(['first-recovery-code', 'second-recovery-code'])),
    ])->save();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login.store'), [
        'recovery_code' => 'first-recovery-code',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);

    $stored = json_decode(decrypt($user->refresh()->two_factor_recovery_codes), true);

    expect($stored)->not->toContain('first-recovery-code')
        ->toContain('second-recovery-code')
        ->toHaveCount(2);
});
