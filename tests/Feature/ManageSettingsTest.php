<?php

use App\Filament\Pages\ManageSettings;
use App\Mail\TestEmail;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Settings\SettingsTab;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Facades\Mail;
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
    // field would be silently uneditable rather than failing loudly. Traversal
    // includes hidden components: fields conditional on another field's value
    // are still declared, still editable, and must still exist.
    $fields = array_keys(
        Livewire::test(ManageSettings::class)->instance()->form->getFlatFields(withHidden: true)
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
        ->assertSee('Registration')
        ->assertSee('Email');
});

test('each setting is edited on its declared tab', function () {
    // The tabs are built from the enums, so a key whose tab has no matching
    // tab case -- or a tab whose keys are claimed by no case -- is a wiring
    // mistake worth failing loudly on rather than an uneditable setting.
    // Fields are listed with hidden ones included, since a field conditional
    // on the mailer still belongs to its tab while hidden.
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
                $tab->getChildSchema()->getComponents(withHidden: true),
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

test('the form opens on the stored mail settings', function () {
    app(Settings::class)->setMany([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.example.com',
        'mail_port' => 587,
        'mail_username' => 'mailer',
        'mail_password' => 'secret',
        'mail_encryption' => 'tls',
        'mail_from_address' => 'hello@cromulent.test',
        'mail_from_name' => 'Cromulent No-reply',
    ]);

    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet([
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => '587',
            'mail_username' => 'mailer',
            'mail_password' => 'secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'hello@cromulent.test',
            'mail_from_name' => 'Cromulent No-reply',
        ]);
});

test('saving the form persists the mail settings', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm([
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_username' => 'mailer',
            'mail_password' => 'secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'hello@cromulent.test',
            'mail_from_name' => 'Cromulent No-reply',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::MailMailer))->toBe('smtp')
        ->and($settings->string(SettingKey::MailHost))->toBe('smtp.example.com')
        ->and($settings->string(SettingKey::MailPort))->toBe('587')
        ->and($settings->string(SettingKey::MailUsername))->toBe('mailer')
        ->and($settings->string(SettingKey::MailPassword))->toBe('secret')
        ->and($settings->string(SettingKey::MailEncryption))->toBe('tls')
        ->and($settings->string(SettingKey::MailFromAddress))->toBe('hello@cromulent.test')
        ->and($settings->string(SettingKey::MailFromName))->toBe('Cromulent No-reply');
});

test('the mail port is rejected when it is not a port number', function () {
    // The connection fields only validate while visible, so the mailer is
    // switched to SMTP first.
    Livewire::test(ManageSettings::class)
        ->fillForm(['mail_mailer' => 'smtp', 'mail_port' => 99999])
        ->call('save')
        ->assertHasFormErrors(['mail_port']);
});

test('the smtp connection fields appear only while the mailer is smtp', function () {
    $connectionFields = ['mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption'];

    $component = Livewire::test(ManageSettings::class);

    foreach ($connectionFields as $field) {
        $component->assertSchemaComponentHidden($field);
    }

    // The from fields stay visible for both mailers, since the from address
    // is applied whichever one sends.
    $component->assertSchemaComponentVisible('mail_from_address')
        ->assertSchemaComponentVisible('mail_from_name');

    $component->fillForm(['mail_mailer' => 'smtp']);

    foreach ($connectionFields as $field) {
        $component->assertSchemaComponentVisible($field);
    }
});

test('switching the mailer back to log keeps the stored connection', function () {
    // Moving to log must not clear the SMTP row: switching back should
    // reveal the connection exactly as it was left, the same way the
    // incomplete-row fallback does not destroy anything.
    app(Settings::class)->setMany([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.example.com',
        'mail_username' => 'mailer',
        'mail_password' => 's3cret',
    ]);

    Livewire::test(ManageSettings::class)
        ->fillForm(['mail_mailer' => 'log'])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::MailMailer))->toBe('log')
        ->and($settings->string(SettingKey::MailHost))->toBe('smtp.example.com')
        ->and($settings->string(SettingKey::MailUsername))->toBe('mailer')
        ->and($settings->string(SettingKey::MailPassword))->toBe('s3cret');
});

test('the from fields hint at the value a blank falls back to', function () {
    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');

    $fields = Livewire::test(ManageSettings::class)->instance()->form->getFlatFields(withHidden: true);

    expect($fields['mail_from_address']->getPlaceholder())->toBe(config('mail.from.address'))
        ->and($fields['mail_from_name']->getPlaceholder())->toBe('Cromulent Widgets');
});

test('the test email action delivers the message to the chosen address', function () {
    Mail::fake();

    // The fake swaps the mail manager before the settings-driven override can
    // resolve, so the branch the notification reports on is configured here.
    config(['mail.default' => 'smtp']);

    Livewire::test(ManageSettings::class)
        ->callAction('testEmail', ['recipient' => 'postmaster@cromulent.test']);

    Mail::assertSent(TestEmail::class, fn (TestEmail $mail): bool => $mail->hasTo('postmaster@cromulent.test'));

    Notification::assertNotified('Test email sent');
});

test('the test email action reports when the message only reached the log', function () {
    Mail::fake();
    config(['mail.default' => 'log']);

    Livewire::test(ManageSettings::class)
        ->callAction('testEmail', ['recipient' => 'postmaster@cromulent.test']);

    Notification::assertNotified('Test email written to the log');
});
