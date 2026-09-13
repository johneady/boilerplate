<?php

use AchyutN\FilamentLogViewer\LogTable;
use App\Filament\Pages\ManageSettings;
use App\Jobs\ProcessUploadedImage;
use App\Mail\TestEmail;
use App\Media\StagedUpload;
use App\Models\User;
use App\Settings\DiagnosticResult;
use App\Settings\DiagnosticSeverity;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Settings\SettingsTab;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

/**
 * The built tab for a settings group, badge included.
 *
 * The badge's closures resolve when read, so the tab an assertion inspects
 * describes the state at the moment of the call -- exactly what the strip
 * renders.
 */
function settingsPageTab(SettingsTab $settingsTab): Tab
{
    $flattener = function (array $components) use (&$flattener): array {
        $result = [];

        foreach ($components as $component) {
            $result[] = $component;

            if ($component instanceof Component) {
                $result = [...$result, ...$flattener($component->getChildComponents())];
            }
        }

        return $result;
    };

    $tab = collect($flattener(Livewire::test(ManageSettings::class)->instance()->form->getComponents()))
        ->filter(fn (object $component): bool => $component instanceof Tab)
        ->first(fn (Tab $candidate): bool => $candidate->getLabel() === $settingsTab->label());

    expect($tab)->not->toBeNull("No tab built for [{$settingsTab->label()}].");

    return $tab;
}

test('the panel navigation links to the settings page', function () {
    $this->get('/admin/settings')->assertSuccessful();
});

test('the settings item sits in the System group above the log viewer', function () {
    expect(ManageSettings::getNavigationGroup())->toBe('System')
        ->and(ManageSettings::getNavigationSort())->toBeLessThan(LogTable::getNavigationSort());
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
        'seo_title',
        'seo_description',
        'allow_search_indexing',
        'allow_registration',
        'mail_from_address',
        'mail_from_name',
        'ops_alert_email',
        'timezone',
        'locale',
        'date_format',
        'time_format',
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
    $page = Livewire::test(ManageSettings::class);

    foreach (SettingsTab::cases() as $settingsTab) {
        $page->assertSee($settingsTab->label());
    }
});

/**
 * The Server tab reports on the machine rather than the settings table, so
 * like the Diagnostics tab it has no fields of its own -- these cover that
 * its findings actually reach the page, and that a host which exposes less
 * (no web server name under the CLI) still renders the rest.
 */
test('the server tab reports the build it is running on', function () {
    Livewire::test(ManageSettings::class)
        ->assertSee('Server report')
        ->assertSee(PHP_VERSION)
        ->assertSee(app()->version())
        ->assertSee(PHP_OS_FAMILY)
        ->assertSee('Loaded extensions');
});

test('the server tab reports the database behind the site', function () {
    Livewire::test(ManageSettings::class)
        ->assertSee('Database')
        ->assertSee('sqlite');
});

/**
 * The Diagnostics tab reports on the environment rather than the settings
 * table, so it has no fields of its own -- these cover that its findings
 * actually reach the page, since a tab rendering nothing would look identical
 * to a healthy one.
 */
test('the diagnostics tab reports a failing check', function () {
    config()->set('app.debug', true);

    Livewire::test(ManageSettings::class)
        ->assertSee('Debug mode')
        ->assertSee('Any error renders a stack trace');
});

test('the diagnostics tab reports the environment it audited', function () {
    config()->set('app.env', 'production');

    Livewire::test(ManageSettings::class)
        ->assertSee('Environment: production');
});

test('the diagnostics tab reports when every check passes', function () {
    config()->set('app.debug', false);

    Livewire::test(ManageSettings::class)
        ->assertSee('No issues found');
});

/**
 * The tab badge is the headline the report's own badges would otherwise
 * bury: a failing check is visible on the tab strip without opening the
 * tab. The environment the checks read is pinned to a single error, since
 * the badge counts every failure at once.
 */
test('the diagnostics tab is flagged with the count of failing checks', function () {
    config()->set('app.debug', true);

    $tab = settingsPageTab(SettingsTab::Diagnostics);

    expect($tab->getBadge())->toBe('1')
        ->and($tab->getBadgeColor())->toBe('danger')
        ->and($tab->getBadgeTooltip())->toBe('1 error');
});

test('the diagnostics tab carries no flag when every check passes', function () {
    config()->set('app.debug', false);

    expect(settingsPageTab(SettingsTab::Diagnostics)->getBadge())->toBeNull();
});

/**
 * Pinned independently of the configuration that produces the findings, so
 * the amber errors-take-precedence branch and the pluralised breakdown are
 * exercised without having to stage a warnings-only production environment.
 */
test('the diagnostics flag reads directly off its findings', function (array $severities, ?string $label, string $color, ?string $tooltip) {
    $failures = array_map(
        fn (DiagnosticSeverity $severity): DiagnosticResult => new DiagnosticResult('Check', false, $severity, 'detail'),
        $severities,
    );

    expect(ManageSettings::diagnosticsBadge($failures))->toBe([
        'label' => $label,
        'color' => $color,
        'tooltip' => $tooltip,
    ]);
})->with([
    'no failures' => [[], null, 'warning', null],
    'one error' => [[DiagnosticSeverity::Error], '1', 'danger', '1 error'],
    'many errors' => [[DiagnosticSeverity::Error, DiagnosticSeverity::Error], '2', 'danger', '2 errors'],
    'one warning' => [[DiagnosticSeverity::Warning], '1', 'warning', '1 warning'],
    'warnings only' => [[DiagnosticSeverity::Warning, DiagnosticSeverity::Warning], '2', 'warning', '2 warnings'],
    'mixed' => [[DiagnosticSeverity::Error, DiagnosticSeverity::Error, DiagnosticSeverity::Warning], '3', 'danger', '2 errors, 1 warning'],
]);

test('non-admins cannot reach the diagnostics report', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/settings')
        ->assertForbidden();
});

test('each setting is edited on its declared tab', function () {
    // The tabs are built from the enums, so a key whose tab has no matching
    // tab case -- or a tab whose keys are claimed by no case -- is a wiring
    // mistake worth failing loudly on rather than an uneditable setting.
    // Fields are listed with hidden ones included, since a field conditional
    // on the mailer still belongs to its tab while hidden. The mailer group
    // is edited through the mailer button's modal rather than tab fields.
    // The mailer group is edited through the mailer button's modal, and the
    // site icon through the Brand tab's buttons, rather than tab fields.
    $editedInModal = ['mail_mailer', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', 'logo'];

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

/**
 * The Email tab's badge is the tab-strip flag for what the mailer button
 * reports once the tab is open: while mail does not go out through SMTP,
 * the mailer actually sending is named beside the tab label.
 */
test('the email tab is flagged with the mailer in use while it is not smtp', function () {
    config(['mail.default' => 'log']);

    $tab = settingsPageTab(SettingsTab::Mail);

    expect($tab->getBadge())->toBe('Log')
        ->and($tab->getBadgeColor())->toBe('warning')
        ->and($tab->getBadgeTooltip())->toBe('Messages are sent through Log rather than SMTP. Change it with the mailer button on this tab.');
});

test('the email tab carries no flag while the mailer is smtp', function () {
    config(['mail.default' => 'smtp']);

    expect(settingsPageTab(SettingsTab::Mail)->getBadge())->toBeNull();
});

test('the email tab flag follows an incomplete smtp choice back to the log', function () {
    app(Settings::class)->setMany([
        'mail_mailer' => 'smtp',
        'mail_host' => '',
    ]);

    expect(settingsPageTab(SettingsTab::Mail)->getBadge())->toBe('Log');
});

test('submitting smtp without a host warns that mail stays on the log', function () {
    // A from address is stored first so the button's from-address guard lets
    // the modal open: this test is about the host warning, which only the
    // action itself -- after the modal opens and submits -- can issue.
    app(Settings::class)->set(SettingKey::MailFromAddress, 'hello@cromulent.test');

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
        'mail_from_address' => 'hello@cromulent.test',
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
    // A from address is stored first: the button is stopped until one is,
    // and the SMTP this test submits goes live on submit.
    app(Settings::class)->set(SettingKey::MailFromAddress, 'hello@cromulent.test');

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

test('the mailer button is stopped until a from address is set', function () {
    // The button is the only path to choosing SMTP, and the modal it opens
    // cannot edit the from fields -- so it never opens while no from
    // address is stored. The administrator is told to add one first rather
    // than being left to fail after the modal opens.
    Livewire::test(ManageSettings::class)
        ->mountAction('configureMailer');

    Notification::assertNotified('Add a from address before changing the mailer');

    app()->forgetInstance(Settings::class);

    // Nothing was submitted because nothing was open to submit.
    expect(app(Settings::class)->has(SettingKey::MailMailer))->toBeFalse();

    Notification::assertNotNotified('Mailer updated');
});

test('a forged submission cannot switch the mailer while no from address is set', function () {
    // The button is stopped in the browser, but actions are server-side:
    // mounting runs the same guard, so a request that calls the action
    // directly is halted before the mailer modal's form ever exists.
    Livewire::test(ManageSettings::class)
        ->callAction('configureMailer', [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.com',
        ]);

    Notification::assertNotified('Add a from address before changing the mailer');

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->has(SettingKey::MailMailer))->toBeFalse();
});

test('the mailer button opens again once a from address is set', function () {
    app(Settings::class)->set(SettingKey::MailFromAddress, 'hello@cromulent.test');

    Livewire::test(ManageSettings::class)
        ->callAction('configureMailer', ['mail_mailer' => 'log'])
        ->assertHasNoActionErrors();

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->string(SettingKey::MailMailer))->toBe('log');

    Notification::assertNotified('Mailer updated');
});

test('the mail port is rejected when it is not a port number', function () {
    // A from address is stored first so the button's guard lets the modal
    // open; the port rule this test pins runs at submission.
    app(Settings::class)->set(SettingKey::MailFromAddress, 'hello@cromulent.test');

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

test('the from address is required while the mailer is smtp', function () {
    // The mailer choice is persisted by the modal before the form ever
    // saves, so the field keys off the stored row: with SMTP live, a blank
    // would send real mail from an address nobody chose.
    app(Settings::class)->setMany([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.example.com',
    ]);

    Livewire::test(ManageSettings::class)
        ->fillForm(['mail_from_address' => ''])
        ->call('save')
        ->assertHasFormErrors(['mail_from_address' => 'required']);
});

test('the from address is optional while the mailer is log', function () {
    app(Settings::class)->set(SettingKey::MailMailer, 'log');

    Livewire::test(ManageSettings::class)
        ->fillForm(['mail_from_address' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(Settings::class);

    // A blank row is the meaningful "keep the deployment address" value,
    // not a missing one the cast quietly replaces.
    expect(app(Settings::class)->string(SettingKey::MailFromAddress))->toBe('');
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
        'mail_from_address' => 'hello@cromulent.test',
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

test('saving the form persists the seo settings', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm([
            'seo_title' => 'Cromulent Widgets',
            'seo_description' => 'Purveyors of fine example widgets.',
            'allow_search_indexing' => false,
            'mail_from_address' => 'hello@cromulent.test',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::SeoTitle))->toBe('Cromulent Widgets')
        ->and($settings->string(SettingKey::SeoDescription))->toBe('Purveyors of fine example widgets.')
        ->and($settings->boolean(SettingKey::AllowSearchIndexing))->toBeFalse();
});

test('the seo description is capped at a search-result length', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm([
            'seo_description' => str_repeat('x', 512),
            'mail_from_address' => 'hello@cromulent.test',
        ])
        ->call('save')
        ->assertHasFormErrors(['seo_description' => 'max']);
});

test('the seo title hints at the value a blank falls back to', function () {
    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');

    $fields = Livewire::test(ManageSettings::class)->instance()->form->getFlatFields(withHidden: true);

    expect($fields['seo_title']->getPlaceholder())->toBe('Cromulent Widgets');
});

/**
 * The buttons alone cannot convey which mark is actually in use, and the most
 * common state -- nothing uploaded, the bundled mark in use -- is exactly the
 * one an administrator needs to see before deciding to replace it.
 */
test('the seo and brand tab previews the bundled mark when no logo is stored', function () {
    Livewire::test(ManageSettings::class)
        ->assertSee('Default logo')
        ->assertDontSee('Your logo');
});

test('the seo and brand tab previews the uploaded logo once one is stored', function () {
    Storage::fake('public');

    $this->storeLogo();

    Livewire::test(ManageSettings::class)
        ->assertSee('Your logo')
        ->assertDontSee('Default logo')
        ->assertSee('/storage/logo/abc/mark.webp');
});

test('the logo button offers an upload until a logo is stored', function () {
    Livewire::test(ManageSettings::class)
        ->assertSee('Upload logo')
        ->assertActionHidden('removeLogo');
});

test('the logo button offers a replacement once a logo is stored', function () {
    Storage::fake('public');

    $this->storeLogo();

    Livewire::test(ManageSettings::class)
        ->assertSee('Replace logo')
        ->assertActionVisible('removeLogo');
});

test('submitting the logo modal stages the upload and queues the processing job', function () {
    Queue::fake();
    Storage::fake('local');

    Livewire::test(ManageSettings::class)
        ->callAction('uploadLogo', [
            'logo' => UploadedFile::fake()->image('icon.png', 128, 128),
        ]);

    // The staged source must be on the private disk, never the public one --
    // the unprocessed original is not re-encoded yet.
    Queue::assertPushed(ProcessUploadedImage::class, fn (ProcessUploadedImage $job): bool => $job->conversionSet === 'logo'
        && $job->mediaId !== null
        && str_starts_with($job->sourcePath, 'uploads/pending/'));

    Notification::assertNotified('Logo uploaded');
});

test('the logo modal rejects a file type the processing job cannot decode', function () {
    Queue::fake();

    Livewire::test(ManageSettings::class)
        ->callAction('uploadLogo', [
            'logo' => UploadedFile::fake()->create('icon.svg', 64, 'image/svg+xml'),
        ]);

    Queue::assertNotPushed(ProcessUploadedImage::class);
});

/**
 * FileUpload state is client-controllable once dehydrated, so a forged
 * request can submit an arbitrary path string instead of a fresh upload --
 * the vector BaseFileUpload's own docblock warns about. Filament's file
 * validation currently rejects such a string before the action runs, but
 * only paths inside the staging directory may EVER reach the job, or
 * anything readable on the private disk could be republished (or deleted)
 * through this action.
 */
test('only freshly staged uploads qualify as logo sources', function (string $path, bool $qualifies) {
    // The rule lives in the media layer, which is where MediaManager enforces
    // it on every adoption -- this asserts the panel's upload obeys the same
    // predicate rather than a copy of it.
    expect(StagedUpload::isStagedPath($path))->toBe($qualifies);
})->with([
    'a staged upload' => ['uploads/pending/abc123.png', true],
    'an avatar conversion' => ['avatars/1/abc/thumb.webp', false],
    'a traversal out of staging' => ['uploads/pending/../../private/.env', false],
    'a bare parent directory' => ['uploads/pending/..', false],
    'a hidden file' => ['uploads/pending/.env', false],
    'a nested path' => ['uploads/pending/nested/source.png', false],
    'an absolute path' => ['/etc/passwd', false],
    'an empty path' => ['', false],
]);

/**
 * The logo is a media row rather than a settings value, so saving the settings
 * form cannot carry a stale copy of it -- there is no logo key in the form
 * state to write back. This pins that: a save between storing a logo and
 * reading it leaves the stored one in place.
 */
test('saving the form does not disturb a logo the modal just stored', function () {
    Storage::fake('public');

    $logo = $this->storeLogo('logo/new');

    Livewire::test(ManageSettings::class)
        ->fillForm(['mail_from_address' => 'hello@cromulent.test'])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->logoMedia()?->getKey())->toBe($logo->getKey());

    Notification::assertNotified('Settings saved');
});

test('removing the logo deletes its row and its files', function () {
    Storage::fake('public');

    $this->storeLogo();

    Livewire::test(ManageSettings::class)
        ->callAction('removeLogo');

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->logoMedia())->toBeNull();
    Storage::disk('public')->assertMissing('logo/abc/favicon.webp');

    Notification::assertNotified('Logo removed');
});

/**
 * The remove action persists out-of-band and re-renders the page in the same
 * request. The preview must describe the logo now in use, not the one the
 * schema was built with, or an administrator is told their upload is still
 * live immediately after deleting it.
 */
test('the preview falls back to the bundled mark once the logo is removed', function () {
    Storage::fake('public');

    $this->storeLogo();

    Livewire::test(ManageSettings::class)
        ->assertSee('Your logo')
        ->callAction('removeLogo')
        ->assertSee('Default logo')
        ->assertDontSee('Your logo');
});

test('the test email modal defaults to the business contact address', function () {
    app(Settings::class)->set(SettingKey::BusinessEmail, 'office@cromulent.test');

    Livewire::test(ManageSettings::class)
        ->mountAction('testEmail')
        ->assertActionDataSet(['recipient' => 'office@cromulent.test']);
});

test('the test email modal falls back to the administrator when no business contact address is set', function () {
    Livewire::test(ManageSettings::class)
        ->mountAction('testEmail')
        ->assertActionDataSet(['recipient' => $this->admin->email]);
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

test('the locale and time tab opens on the stored values', function () {
    app(Settings::class)->setMany([
        'timezone' => 'Australia/Sydney',
        'locale' => 'fr',
        'date_format' => 'd/m/Y',
        'time_format' => 'g:i a',
    ]);

    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet([
            'timezone' => 'Australia/Sydney',
            'locale' => 'fr',
            'date_format' => 'd/m/Y',
            'time_format' => 'g:i a',
        ]);
});

test('saving the form persists the locale and time settings', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm([
            'mail_from_address' => 'hello@cromulent.test',
            'timezone' => 'Australia/Sydney',
            'locale' => 'de',
            'date_format' => 'Y-m-d',
            'time_format' => 'g:i A',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::Timezone))->toBe('Australia/Sydney')
        ->and($settings->string(SettingKey::Locale))->toBe('de')
        ->and($settings->string(SettingKey::DateFormat))->toBe('Y-m-d')
        ->and($settings->string(SettingKey::TimeFormat))->toBe('g:i A');
});

test('the timezone select offers the identifiers grouped by region', function () {
    $fields = Livewire::test(ManageSettings::class)->instance()->form->getFlatFields(withHidden: true);

    /** @var array<string, mixed> $options */
    $options = $fields['timezone']->getOptions();

    expect($options)->toHaveKey('UTC')
        ->and($options['Australia'])->toBeArray()
        ->and($options['Australia'])->toHaveKey('Australia/Sydney');
});

test('the format examples demonstrate each candidate format', function () {
    Livewire::test(ManageSettings::class)
        ->assertSee('5 Mar 2021')
        ->assertSee('5 March 2021')
        ->assertSee('05/03/2021')
        ->assertSee('03/05/2021')
        ->assertSee('05.03.2021')
        ->assertSee('05-03-2021')
        ->assertSee('2021-03-05')
        ->assertSee('14:07')
        ->assertSee('2:07 pm')
        ->assertSee('2:07 PM');
});

test('the format examples name the convention each option belongs to', function () {
    // The notes are what let an administrator pick a convention rather than
    // decode format characters: an example alone says what it looks like,
    // not who writes dates that way.
    Livewire::test(ManageSettings::class)
        ->assertSee('Month first — United States')
        ->assertSee('Day first — UK, Australia')
        ->assertSee('ISO 8601 — Canada, sortable')
        ->assertSee('24-hour')
        ->assertSee('12-hour, lowercase am/pm — Default');
});

test('the date format examples follow the locale as it is chosen', function () {
    // Locale drives the names while the format drives the shape; the live
    // re-render is what shows an administrator the two interact before
    // anything is saved.
    Livewire::test(ManageSettings::class)
        ->set('data.locale', 'fr')
        ->assertSee('5 mars 2021')
        ->assertDontSee('5 Mar 2021');
});
