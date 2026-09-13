<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Livewire\Settings\Security;
use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('changing a password notifies the account owner', function () {
    Notification::fake();

    $user = User::factory()->create();

    $user->forceFill(['password' => 'new-password-value'])->save();

    Notification::assertSentTo($user, PasswordChanged::class);
});

test('creating a user does not send a password alert', function () {
    // A first password is not a change, and warning every new account that
    // its password "was changed" is both wrong and alarming.
    Notification::fake();

    User::factory()->create();

    Notification::assertNothingSent();
});

test('saving a user without touching the password sends nothing', function () {
    Notification::fake();

    $user = User::factory()->create();

    $user->forceFill(['name' => 'A New Name'])->save();

    Notification::assertNothingSent();
});

test('re-saving the same password sends nothing', function () {
    // Eloquent compares the hashed value, so a no-op save must not look like
    // a change -- otherwise any profile form that round-trips the password
    // field would alarm the user on every save.
    Notification::fake();

    $user = User::factory()->create();
    $existing = $user->password;

    $user->forceFill(['password' => $existing])->save();

    Notification::assertNothingSent();
});

test('the settings security form notifies on password update', function () {
    // The in-app path: a signed-in user changing their own password.
    Notification::fake();

    $user = User::factory()->create(['password' => 'old-password']);

    Livewire::actingAs($user)
        ->test(Security::class)
        ->set('current_password', 'old-password')
        ->set('password', 'a-brand-new-password')
        ->set('password_confirmation', 'a-brand-new-password')
        ->call('updatePassword')
        ->assertHasNoErrors();

    Notification::assertSentTo($user, PasswordChanged::class);
});

test('a forgotten-password reset notifies too', function () {
    // The other path, which is the one that matters most: a reset is what an
    // attacker uses, and the owner finding out is the entire point.
    Notification::fake();

    $user = User::factory()->create();

    app(ResetUserPassword::class)->reset($user, [
        'password' => 'a-reset-password-value',
        'password_confirmation' => 'a-reset-password-value',
    ]);

    Notification::assertSentTo($user, PasswordChanged::class);
});

test('the alert is queued so it does not block the request', function () {
    expect(new PasswordChanged)->toBeInstanceOf(ShouldQueue::class);
});

test('the alert carries no link for the reader to click', function () {
    // A security warning that invites a click trains the exact reflex that
    // phishing depends on.
    $user = User::factory()->create();

    $rendered = (new PasswordChanged('203.0.113.9'))->toMail($user)->render();

    expect(str_contains($rendered, 'password/reset'))->toBeFalse();

    expect((new PasswordChanged('203.0.113.9'))->toMail($user)->actionUrl)->toBeNull();
});

test('the mail subject is suffixed with the business name', function () {
    // The base class adds the suffix so an alert is identifiable in an inbox
    // receiving them from more than one installation.
    $user = User::factory()->create();

    app(Settings::class)->set(SettingKey::BusinessName, 'Probe Industries');

    $subject = (new PasswordChanged)->toMail($user)->subject;

    expect($subject)->toContain('Probe Industries');
});

test('notifications brand from the business name, not APP_NAME', function () {
    config()->set('app.name', 'SHOULD-NOT-APPEAR');
    app(Settings::class)->set(SettingKey::BusinessName, 'Probe Industries');

    $user = User::factory()->create();
    $rendered = (new PasswordChanged)->toMail($user)->render();

    expect(str_contains($rendered, 'SHOULD-NOT-APPEAR'))->toBeFalse()
        ->and(str_contains($rendered, 'Probe Industries'))->toBeTrue();
});
