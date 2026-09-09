<?php

use App\Livewire\Settings\TwoFactor\RecoveryCodes;
use App\Models\User;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('recovery codes are shown for a user with two factor authentication', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user);

    Livewire::test(RecoveryCodes::class)
        ->assertSet('recoveryCodes', ['recovery-code-1'])
        ->assertSee('recovery-code-1');
});

test('recovery codes can be regenerated', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user);

    Livewire::test(RecoveryCodes::class)
        ->call('regenerateRecoveryCodes')
        ->assertDontSee('recovery-code-1');

    $stored = json_decode(decrypt($user->refresh()->two_factor_recovery_codes), true);

    expect($stored)->not->toContain('recovery-code-1')
        ->toHaveCount(8);
});

test('a corrupted recovery codes row fails gracefully instead of crashing', function () {
    $user = User::factory()->withTwoFactor()->create();

    // Decrypts fine but is not valid JSON: json_decode returns null rather
    // than throwing, which previously raised an uncatchable TypeError on the
    // typed array property.
    $user->forceFill([
        'two_factor_recovery_codes' => encrypt('corrupt{json'),
    ])->save();

    $this->actingAs($user);

    Livewire::test(RecoveryCodes::class)
        ->assertSet('recoveryCodes', [])
        ->assertHasErrors(['recoveryCodes']);
});
