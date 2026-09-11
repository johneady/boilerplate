<?php

use App\Filament\Pages\ManageSettings;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Settings\SettingsTab;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
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

test('the form opens on the stored business name', function () {
    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');

    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet(['business_name' => 'Cromulent Widgets']);
});

test('saving the form persists the business name', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm(['business_name' => 'Cromulent Widgets'])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->businessName())->toBe('Cromulent Widgets');
});

test('the business name is required', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm(['business_name' => ''])
        ->call('save')
        ->assertHasFormErrors(['business_name' => 'required']);
});

test('the settings page renders a tab for each declared group of settings', function () {
    Livewire::test(ManageSettings::class)
        ->assertSee('Business details')
        ->assertSee('Registration');
});

test('each setting is edited on its declared tab', function () {
    // The tabs are built from the enums, so a key whose tab has no matching
    // tab case -- or a tab whose keys are claimed by no case -- is a wiring
    // mistake worth failing loudly on rather than an uneditable setting.
    $flattener = function (array $components) use (&$flattener): array {
        $result = [];

        foreach ($components as $component) {
            $result[] = $component;
            $result = [...$result, ...$flattener($component->getChildComponents())];
        }

        return $result;
    };

    $tabs = collect($flattener(Livewire::test(ManageSettings::class)->instance()->form->getComponents()))
        ->filter(fn (Component $component): bool => $component instanceof Tab);

    expect($tabs)->toHaveCount(count(SettingsTab::cases()));

    foreach (SettingsTab::cases() as $settingsTab) {
        $tab = $tabs->first(fn (Tab $tab): bool => $tab->getLabel() === $settingsTab->label());

        $keysOnTab = array_values(array_map(
            fn (SettingKey $key): string => $key->value,
            array_filter(SettingKey::cases(), fn (SettingKey $key): bool => $key->tab() === $settingsTab),
        ));

        expect($tab)->not->toBeNull()
            ->and(array_map(
                fn (Component $field): string => $field->getName(),
                $tab->getChildComponents(),
            ))->toBe($keysOnTab);
    }
});

test('saving the form persists the business contact details', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm([
            'business_address' => "1 Cromulent Way\nWidgetton",
            'business_phone' => '+1 (555) 123-4567',
            'business_email' => 'hello@cromulent.test',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::BusinessAddress))->toBe("1 Cromulent Way\nWidgetton")
        ->and($settings->string(SettingKey::BusinessPhone))->toBe('+1 (555) 123-4567')
        ->and($settings->string(SettingKey::BusinessEmail))->toBe('hello@cromulent.test');
});

test('the business email is rejected when it is not an address', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm(['business_email' => 'not-an-address'])
        ->call('save')
        ->assertHasFormErrors(['business_email' => 'email']);
});

test('blanking a contact detail clears it rather than leaving it set', function () {
    // Clearing a detail is how an administrator hides it from the footer, so
    // the saved row must read back as the empty string -- whether the request
    // delivered it as "", null (ConvertEmptyStringsToNull), or whitespace.
    app(Settings::class)->set(SettingKey::BusinessPhone, '+1 (555) 123-4567');

    Livewire::test(ManageSettings::class)
        ->fillForm(['business_phone' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->string(SettingKey::BusinessPhone))->toBe('');
});
