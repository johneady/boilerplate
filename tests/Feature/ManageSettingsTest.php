<?php

use App\Filament\Pages\ManageSettings;
use App\Mail\TestEmail;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Settings\SettingsTab;
use Filament\Forms\Components\Field;
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
        ->fillForm(['allow_registration' => true, 'mail_from_address' => 'hello@cromulent.test'])
        ->call('save')
        ->assertHasNoFormErrors();

    // Resolved afresh, as the next request would, rather than through the
    // instance the page just wrote to.
    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->boolean(SettingKey::AllowRegistration))->toBeTrue();
});

test('saving the form confirms the change to the administrator', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm(['allow_registration' => true, 'mail_from_address' => 'hello@cromulent.test'])
        ->call('save');

    Notification::assertNotified('Settings saved');
});

test('a setting can be turned back off', function () {
    app(Settings::class)->set(SettingKey::AllowRegistration, true);

    Livewire::test(ManageSettings::class)
        ->fillForm(['allow_registration' => false, 'mail_from_address' => 'hello@cromulent.test'])
        ->call('save');

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->boolean(SettingKey::AllowRegistration))->toBeFalse();
});

test('the form renders a field for every setting the mailer modal does not edit', function () {
    // The form is built from the enum, so a case added later without a matching
    // field would be silently uneditable rather than failing loudly. The
    // mailer group is the exception -- it is edited through the mailer
    // button's modal, held to the same standard by the modal tests below.
    // Traversal includes hidden components.
    $fields = array_keys(
        Livewire::test(ManageSettings::class)->instance()->form->getFlatFields(withHidden: true)
    );

    expect($fields)->toBe([
        'business_name',
        'business_address',
        'business_phone',
        'business_email',
        'allow_registration',
        'mail_from_address',
        'mail_from_name',
    ]);
});

test('the form opens on the stored business name', function () {
    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');

    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet(['business_name' => 'Cromulent Widgets']);
});

test('saving the form persists the business name', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm(['business_name' => 'Cromulent Widgets', 'mail_from_address' => 'hello@cromulent.test'])
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
    // on the mailer still belongs to its tab while hidden. The mailer group
    // is edited through the mailer button's modal rather than tab fields.
    $editedInModal = ['mail_mailer', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption'];

    $flattener = function (array $components) use (&$flattener): array {
        $result = [];

        foreach ($components as $component) {
            $result[] = $component;

            // The mailer button nests a Filament action, which is not a
            // schema component and has no children of its own.
            if ($component instanceof Component) {
                $result = [...$result, ...$flattener($component->getChildComponents())];
            }
        }

        return $result;
    };

    $tabs = collect($flattener(Livewire::test(ManageSettings::class)->instance()->form->getComponents()))
        ->filter(fn (object $component): bool => $component instanceof Tab);

    expect($tabs)->toHaveCount(count(SettingsTab::cases()));

    foreach (SettingsTab::cases() as $settingsTab) {
        $tab = $tabs->first(fn (Tab $tab): bool => $tab->getLabel() === $settingsTab->label());

        $keysOnTab = array_values(array_map(
            fn (SettingKey $key): string => $key->value,
            array_filter(
                SettingKey::cases(),
                fn (SettingKey $key): bool => $key->tab() === $settingsTab && ! in_array($key->value, $editedInModal, true),
            ),
        ));

        expect($tab)->not->toBeNull()
            ->and(array_values(array_map(
                fn (Field $field): string => $field->getName(),
                array_filter(
                    $tab->getChildSchema()->getComponents(withHidden: true),
                    fn (object $component): bool => $component instanceof Field,
                ),
            )))->toBe($keysOnTab);
    }
});

test('saving the form persists the business contact details', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm([
            'business_address' => "1 Cromulent Way\nWidgetton",
            'business_phone' => '+1 (555) 123-4567',
            'business_email' => 'hello@cromulent.test',
            'mail_from_address' => 'hello@cromulent.test',
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
        ->fillForm(['business_email' => 'not-an-address', 'mail_from_address' => 'hello@cromulent.test'])
        ->call('save')
        ->assertHasFormErrors(['business_email' => 'email']);
});

test('blanking a contact detail clears it rather than leaving it set', function () {
    // Clearing a detail is how an administrator hides it from the footer, so
    // the saved row must read back as the empty string -- whether the request
    // delivered it as "", null (ConvertEmptyStringsToNull), or whitespace.
    app(Settings::class)->set(SettingKey::BusinessPhone, '+1 (555) 123-4567');

    Livewire::test(ManageSettings::class)
        ->fillForm(['business_phone' => '', 'mail_from_address' => 'hello@cromulent.test'])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->string(SettingKey::BusinessPhone))->toBe('');
});

test('the form opens on the stored from fields', function () {
    app(Settings::class)->setMany([
        'mail_from_address' => 'hello@cromulent.test',
        'mail_from_name' => 'Cromulent No-reply',
    ]);

    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet([
            'mail_from_address' => 'hello@cromulent.test',
            'mail_from_name' => 'Cromulent No-reply',
        ]);
});

test('the mailer button shows the environment mailer until one is saved', function () {
    // A deployment configures SMTP through MAIL_* with nothing stored, and
    // the environment stays in charge -- the button must not report the
    // setting's unsaved 'log' default over the mailer actually sending.
    config(['mail.default' => 'smtp']);

    Livewire::test(ManageSettings::class)
        ->assertSee('Mailer: SMTP')
        ->assertActionVisible('configureMailer');
});

test('the mailer button shows the saved mailer once one is', function () {
    app(Settings::class)->setMany([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.example.com',
    ]);

    Livewire::test(ManageSettings::class)
        ->assertSee('Mailer: SMTP');
});

test('an smtp choice without a host shows as the log mailer it falls back to', function () {
    app(Settings::class)->setMany([
        'mail_mailer' => 'smtp',
        'mail_host' => '',
    ]);

    Livewire::test(ManageSettings::class)
        ->assertSee('Mailer: Log');
});

test('submitting smtp without a host warns that mail stays on the log', function () {
    Livewire::test(ManageSettings::class)
        ->callAction('configureMailer', [
            'mail_mailer' => 'smtp',
            'mail_host' => '',
        ]);

    Notification::assertNotified('Mailer saved, sending through the log');
});

test('the mailer modal opens on the stored connection', function () {
    app(Settings::class)->setMany([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.example.com',
        'mail_port' => 587,
        'mail_username' => 'mailer',
        'mail_password' => 'secret',
        'mail_encryption' => 'tls',
    ]);

    Livewire::test(ManageSettings::class)
        ->mountAction('configureMailer')
        ->assertActionDataSet([
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => '587',
            'mail_username' => 'mailer',
            'mail_password' => 'secret',
            'mail_encryption' => 'tls',
        ]);
});

test('submitting the mailer modal persists the mail settings', function () {
    Livewire::test(ManageSettings::class)
        ->callAction('configureMailer', [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_username' => 'mailer',
            'mail_password' => 'secret',
            'mail_encryption' => 'tls',
        ]);

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::MailMailer))->toBe('smtp')
        ->and($settings->string(SettingKey::MailHost))->toBe('smtp.example.com')
        ->and($settings->string(SettingKey::MailPort))->toBe('587')
        ->and($settings->string(SettingKey::MailUsername))->toBe('mailer')
        ->and($settings->string(SettingKey::MailPassword))->toBe('secret')
        ->and($settings->string(SettingKey::MailEncryption))->toBe('tls');

    Notification::assertNotified('Mailer updated');
});

test('the mail port is rejected when it is not a port number', function () {
    // The modal halts on validation, so nothing it edits is stored.
    Livewire::test(ManageSettings::class)
        ->callAction('configureMailer', [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 99999,
        ]);

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->has(SettingKey::MailMailer))->toBeFalse();
});

test('the from address is required', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm(['mail_from_address' => ''])
        ->call('save')
        ->assertHasFormErrors(['mail_from_address' => 'required']);
});

test('the from address is rejected when it is the deployment default', function () {
    // 'hello@example.com' is the framework default the test environment
    // leaves MAIL_FROM_ADDRESS falling back to.
    Livewire::test(ManageSettings::class)
        ->fillForm(['mail_from_address' => 'hello@example.com'])
        ->call('save')
        ->assertHasFormErrors(['mail_from_address' => 'not_in']);
});

test('switching the mailer back to log keeps the stored connection', function () {
    // Moving to log must not clear the SMTP row: switching back should
    // reveal the connection exactly as it was left. The log choice submits
    // without the connection fields, which stay hidden for it, so setMany
    // never receives keys to overwrite.
    app(Settings::class)->setMany([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.example.com',
        'mail_username' => 'mailer',
        'mail_password' => 's3cret',
    ]);

    Livewire::test(ManageSettings::class)
        ->callAction('configureMailer', ['mail_mailer' => 'log']);

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::MailMailer))->toBe('log')
        ->and($settings->string(SettingKey::MailHost))->toBe('smtp.example.com')
        ->and($settings->string(SettingKey::MailUsername))->toBe('mailer')
        ->and($settings->string(SettingKey::MailPassword))->toBe('s3cret');
});

test('the from name hints at the value a blank falls back to', function () {
    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');

    $fields = Livewire::test(ManageSettings::class)->instance()->form->getFlatFields(withHidden: true);

    expect($fields['mail_from_name']->getPlaceholder())->toBe('Cromulent Widgets');
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
