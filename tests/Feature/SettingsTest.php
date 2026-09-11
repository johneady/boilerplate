<?php

use App\Models\Setting;
use App\Settings\SettingKey;
use App\Settings\Settings;

beforeEach(function () {
    $this->settings = app(Settings::class);
});

test('a setting that has never been saved reads back as its declared default', function () {
    expect(Setting::query()->exists())->toBeFalse()
        ->and($this->settings->get(SettingKey::AllowRegistration))->toBeFalse();
});

test('a saved setting is distinguished from an unsaved one', function () {
    // Reading an unsaved key returns the declared default, so has() is the
    // only way to tell "never chosen" from "chosen and left at the default" --
    // the distinction the mail config keys on.
    expect($this->settings->has(SettingKey::MailMailer))->toBeFalse();

    $this->settings->set(SettingKey::MailMailer, 'log');

    expect($this->settings->has(SettingKey::MailMailer))->toBeTrue()
        ->and($this->settings->string(SettingKey::MailMailer))->toBe('log');
});

test('new user registrations are off until an administrator turns them on', function () {
    expect(SettingKey::AllowRegistration->default())->toBeFalse();
});

test('a saved setting is read back', function () {
    $this->settings->set(SettingKey::AllowRegistration, true);

    expect($this->settings->boolean(SettingKey::AllowRegistration))->toBeTrue();
    $this->assertDatabaseHas('settings', ['key' => 'allow_registration']);
});

test('saving a setting twice updates the row rather than adding another', function () {
    $this->settings->set(SettingKey::AllowRegistration, true);
    $this->settings->set(SettingKey::AllowRegistration, false);

    expect(Setting::where('key', 'allow_registration')->count())->toBe(1)
        ->and($this->settings->boolean(SettingKey::AllowRegistration))->toBeFalse();
});

test('a fresh read of a saved setting survives the request cache', function () {
    $this->settings->set(SettingKey::AllowRegistration, true);

    // A second resolution stands in for the next request, proving the value was
    // persisted rather than only held in the singleton's in-memory cache.
    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->boolean(SettingKey::AllowRegistration))->toBeTrue();
});

test('a value written outside the panel is cast to the key\'s declared type', function () {
    // A row hand-edited in the database, or written before the key's type was
    // settled, must still read back as a boolean rather than a string.
    Setting::create(['key' => 'allow_registration', 'value' => 1]);

    expect($this->settings->get(SettingKey::AllowRegistration))->toBeTrue();
});

test('a stored value that is not recognisably true reads as false', function (mixed $stored) {
    // A gate must fail closed. "false" and "off" are both truthy to a plain
    // (bool) cast, so a row hand-edited in a database client would otherwise
    // turn the setting on.
    Setting::create(['key' => 'allow_registration', 'value' => $stored]);

    expect($this->settings->boolean(SettingKey::AllowRegistration))->toBeFalse();
})->with([
    'the string false' => ['false'],
    'the string off' => ['off'],
    'the string zero' => ['0'],
    'an empty string' => [''],
    'an unrecognised value' => ['garbage'],
    'null' => [null],
]);

test('a stored value that is recognisably true reads as true', function (mixed $stored) {
    Setting::create(['key' => 'allow_registration', 'value' => $stored]);

    expect($this->settings->boolean(SettingKey::AllowRegistration))->toBeTrue();
})->with([
    'a real boolean' => [true],
    'the string true' => ['true'],
    'the string one' => ['1'],
    'the string on' => ['on'],
]);

test('settings are re-read between queue jobs rather than held for the life of the worker', function () {
    $this->settings->get(SettingKey::AllowRegistration);

    Setting::create(['key' => 'allow_registration', 'value' => true]);

    // What a queue worker does between jobs. A singleton would survive this and
    // keep serving the value read when the worker booted.
    app()->forgetScopedInstances();

    expect(app(Settings::class)->boolean(SettingKey::AllowRegistration))->toBeTrue();
});

test('a key that is not declared on the enum is not stored', function () {
    $this->settings->setMany([
        'allow_registration' => true,
        'not_a_real_setting' => 'ignored',
    ]);

    expect($this->settings->boolean(SettingKey::AllowRegistration))->toBeTrue();
    $this->assertDatabaseMissing('settings', ['key' => 'not_a_real_setting']);
});

test('every declared setting is present in the array that fills the panel form', function () {
    expect($this->settings->toArray())
        ->toHaveKeys(array_column(SettingKey::cases(), 'value'))
        ->and($this->settings->toArray()['allow_registration'])->toBeFalse();
});

test('the whole table is read once per request however many settings are consulted', function () {
    Setting::create(['key' => 'allow_registration', 'value' => true]);

    DB::enableQueryLog();

    $this->settings->get(SettingKey::AllowRegistration);
    $this->settings->get(SettingKey::AllowRegistration);
    $this->settings->toArray();

    expect(DB::getQueryLog())->toHaveCount(1);
});

test('the business name falls back to the app name when nothing is stored', function () {
    expect(app(Settings::class)->businessName())->toBe(config('app.name'));
});

test('the business name reads back what was stored', function () {
    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');

    app()->forgetScopedInstances();

    expect(app(Settings::class)->businessName())->toBe('Cromulent Widgets');
});

test('a business name stored as blank falls back rather than rendering empty', function (mixed $stored) {
    // The name is rendered on every page, so an empty row would leave the brand
    // blank rather than merely wrong.
    app(Settings::class)->set(SettingKey::BusinessName, $stored);

    app()->forgetScopedInstances();

    expect(app(Settings::class)->businessName())->toBe(config('app.name'));
})->with([
    'empty string' => [''],
    'whitespace' => ['   '],
    'null' => [null],
    'an array' => [['nope']],
]);

test('a stored business name is trimmed', function () {
    app(Settings::class)->set(SettingKey::BusinessName, '  Cromulent Widgets  ');

    app()->forgetScopedInstances();

    expect(app(Settings::class)->businessName())->toBe('Cromulent Widgets');
});

test('unsaved business contact details read as empty strings', function () {
    // The footer hides a detail whose value is empty, so a fresh install with
    // no rows saved must read the declared empty-string defaults.
    expect($this->settings->string(SettingKey::BusinessAddress))->toBe('')
        ->and($this->settings->string(SettingKey::BusinessPhone))->toBe('')
        ->and($this->settings->string(SettingKey::BusinessEmail))->toBe('');
});

test('unsaved mail settings read as the log mailer with no connection details', function () {
    expect($this->settings->string(SettingKey::MailMailer))->toBe('log')
        ->and($this->settings->string(SettingKey::MailHost))->toBe('')
        ->and($this->settings->string(SettingKey::MailPort))->toBe('')
        ->and($this->settings->string(SettingKey::MailUsername))->toBe('')
        ->and($this->settings->string(SettingKey::MailPassword))->toBe('')
        ->and($this->settings->string(SettingKey::MailEncryption))->toBe('')
        ->and($this->settings->string(SettingKey::MailFromAddress))->toBe('')
        ->and($this->settings->string(SettingKey::MailFromName))->toBe('');
});

test('a stored mailer that is not log or smtp reads as log', function (mixed $stored) {
    // Like the registration gate, the mailer must fail closed: a row
    // hand-edited to a typo or a driver this app does not configure must not
    // produce a mailer that cannot resolve.
    Setting::create(['key' => 'mail_mailer', 'value' => $stored]);

    expect($this->settings->string(SettingKey::MailMailer))->toBe('log');
})->with([
    'an empty string' => [''],
    'a typo' => ['smtpx'],
    'an unconfigured driver' => ['ses'],
    'a boolean true' => [true],
    'null' => [null],
]);

test('a stored encryption that is not recognised reads as blank', function (mixed $stored) {
    Setting::create(['key' => 'mail_encryption', 'value' => $stored]);

    expect($this->settings->string(SettingKey::MailEncryption))->toBe('');
})->with([
    'upper case tls' => ['TLS'],
    'a starttls typo' => ['starttls'],
    'a boolean true' => [true],
    'null' => [null],
]);
