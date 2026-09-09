<?php

use App\Filament\Pages\ManageSettings;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Filament\Notifications\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('the panel navigation links to the settings page', function () {
    $this->get('/admin/settings')->assertSuccessful();
});

test('non-admins may not reach the settings page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/settings')
        ->assertForbidden();
});

test('guests are sent to the login page rather than a missing panel login', function () {
    auth()->logout();

    $this->get('/admin/settings')->assertRedirect(route('login'));
});

test('the form opens on the stored values', function () {
    app(Settings::class)->set(SettingKey::AllowRegistration, true);

    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet(['allow_registration' => true]);
});

test('the form opens on the declared defaults when nothing has been saved', function () {
    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet(['allow_registration' => false]);
});

test('saving the form persists the setting', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm(['allow_registration' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    // Resolved afresh, as the next request would, rather than through the
    // instance the page just wrote to.
    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->boolean(SettingKey::AllowRegistration))->toBeTrue();
});

test('saving the form confirms the change to the administrator', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm(['allow_registration' => true])
        ->call('save');

    Notification::assertNotified('Settings saved');
});

test('a setting can be turned back off', function () {
    app(Settings::class)->set(SettingKey::AllowRegistration, true);

    Livewire::test(ManageSettings::class)
        ->fillForm(['allow_registration' => false])
        ->call('save');

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->boolean(SettingKey::AllowRegistration))->toBeFalse();
});

test('the form renders a field for every declared setting', function () {
    // The form is built from the enum, so a case added later without a matching
    // field would be silently uneditable rather than failing loudly.
    $fields = array_keys(
        Livewire::test(ManageSettings::class)->instance()->form->getFlatFields()
    );

    expect($fields)->toBe(array_column(SettingKey::cases(), 'value'));
});
