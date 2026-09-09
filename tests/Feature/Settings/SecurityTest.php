<?php

use App\Livewire\Settings\Security;
use App\Models\User;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
    Features::passkeys([
        'confirmPassword' => true,
    ]);
});

test('security settings page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'));

    $response->assertOk();

    $response->assertSee('Passkeys');
    $response->assertSee('No passkeys yet');
    $response->assertSee('Two-factor authentication');
    $response->assertSee('Enable 2FA');
});

test('security settings page requires password confirmation when enabled', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('security settings page renders without two factor when feature is disabled', function () {
    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Update password')
        ->assertDontSee('Manage your passkeys for passwordless sign-in')
        ->assertDontSee('Add a passkey to sign in without a password')
        ->assertDontSee('Two-factor authentication');
});

test('two factor authentication disabled when confirmation abandoned between requests', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->actingAs($user);

    $component = Livewire::test(Security::class);

    $component->assertSet('twoFactorEnabled', false);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
    ]);
});

test('two factor authentication can be enabled and confirmed with a valid code', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(Security::class)
        ->call('enable');

    $component->assertSet('showModal', true)
        ->assertSet('manualSetupKey', decrypt($user->refresh()->two_factor_secret));

    expect($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeString();

    $component->set('code', app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret)))
        ->call('confirmTwoFactor');

    $component->assertHasNoErrors()
        ->assertSet('twoFactorEnabled', true);

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('two factor authentication cannot be confirmed with an invalid code', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(Security::class)
        ->call('enable');

    // A code generated from a different secret cannot verify against the
    // user's own secret.
    $unrelatedCode = app(Google2FA::class)
        ->getCurrentOtp(app(Google2FA::class)->generateSecretKey());

    $component->set('code', $unrelatedCode)
        ->call('confirmTwoFactor');

    $component->assertHasErrors(['code']);

    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();
});

test('two factor authentication can be disabled', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user);

    Livewire::test(Security::class)
        ->assertSet('twoFactorEnabled', true)
        ->call('disable')
        ->assertSet('twoFactorEnabled', false);

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

test('the password confirmation requirement persists across livewire update requests', function () {
    // Livewire re-runs only the middleware on its persistent list when
    // handling an update request, so the route-level password.confirm gate
    // must be registered there (AppServiceProvider) for this page's actions
    // to stay gated after the initial render.
    expect(Livewire::getPersistentMiddleware())->toContain(RequirePassword::class);
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test(Security::class)
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('updating the password invalidates other sessions and remember tokens', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $originalRememberToken = $user->remember_token;

    // The purge only runs against the database session driver.
    config(['session.driver' => 'database']);

    $this->actingAs($user);

    // A session established on another device.
    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Probe',
        'payload' => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    Livewire::test(Security::class)
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->refresh()->remember_token)->not->toBe($originalRememberToken);
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test(Security::class)
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasErrors(['current_password']);
});
