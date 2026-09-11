<?php

use App\Settings\SettingKey;
use App\Settings\Settings;

beforeEach(function () {
    $this->settings = app(Settings::class);
});

/**
 * The mail settings are applied when the mail manager is first resolved --
 * the same laziness a freshly booted web request or worker has -- so drop any
 * resolved instance and resolve it again.
 */
function resolveFreshMailer(): void
{
    app()->forgetInstance('mail.manager');

    app('mail.manager');
}

test('the mail settings are not applied until the mail manager is resolved', function () {
    $this->settings->setMany([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.example.com',
    ]);

    // 'array' is the framework mailer the test environment configures: still
    // untouched, because nothing has sent mail yet.
    expect(app()->resolved('mail.manager'))->toBeFalse()
        ->and(config('mail.default'))->toBe('array');
});

test('a complete smtp row sends mail through the stored connection', function () {
    $this->settings->setMany([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.fastmail.com',
        'mail_port' => 465,
        'mail_username' => 'mailer@cromulent.test',
        'mail_password' => 's3cret',
        'mail_encryption' => 'ssl',
    ]);

    resolveFreshMailer();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.fastmail.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(465)
        ->and(config('mail.mailers.smtp.username'))->toBe('mailer@cromulent.test')
        ->and(config('mail.mailers.smtp.password'))->toBe('s3cret')
        ->and(config('mail.mailers.smtp.encryption'))->toBe('ssl');
});

test('an smtp row without a host fails closed to the log mailer', function () {
    // Half an SMTP configuration would only surface as transport errors at
    // send time, so mail stays deliverable (to the log) until the row is
    // complete enough to use.
    $this->settings->setMany([
        'mail_mailer' => 'smtp',
        'mail_username' => 'mailer@cromulent.test',
    ]);

    resolveFreshMailer();

    expect(config('mail.default'))->toBe('log');
});

test('the log mailer setting replaces whatever the environment configured', function () {
    config(['mail.default' => 'smtp']);

    $this->settings->set(SettingKey::MailMailer, 'log');

    resolveFreshMailer();

    expect(config('mail.default'))->toBe('log');
});

test('the environment stays in charge until a mailer has been saved', function () {
    // Deployments configure SMTP through MAIL_* (docker/README.md documents
    // exactly that), so an unsaved mailer row must not force the setting's
    // 'log' default over their environment -- their mail would silently stop
    // being delivered. Even a saved connection is inert without the choice.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'env.example.com']);

    $this->settings->set(SettingKey::MailHost, 'panel.example.com');

    resolveFreshMailer();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('env.example.com');
});

test('a blank port keeps the framework default', function () {
    $frameworkDefault = config('mail.mailers.smtp.port');

    $this->settings->setMany([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.example.com',
    ]);

    resolveFreshMailer();

    expect(config('mail.mailers.smtp.port'))->toBe($frameworkDefault);
});

test('blank encryption secures the connection with tls while none disables it', function (string $stored, ?string $applied) {
    $this->settings->setMany([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.example.com',
        'mail_encryption' => $stored,
    ]);

    resolveFreshMailer();

    expect(config('mail.mailers.smtp.encryption'))->toBe($applied);
})->with([
    'blank (framework default)' => ['', 'tls'],
    'explicit tls' => ['tls', 'tls'],
    'ssl' => ['ssl', 'ssl'],
    'none' => ['none', null],
]);

test('the from name falls back to the business name', function () {
    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');

    $this->settings->set(SettingKey::MailFromAddress, 'hello@cromulent.test');

    resolveFreshMailer();

    expect(config('mail.from.address'))->toBe('hello@cromulent.test')
        ->and(config('mail.from.name'))->toBe('Cromulent Widgets');
});

test('an explicit from name wins over the business name', function () {
    $this->settings->setMany([
        'mail_from_address' => 'hello@cromulent.test',
        'mail_from_name' => 'Cromulent No-reply',
    ]);

    resolveFreshMailer();

    expect(config('mail.from.name'))->toBe('Cromulent No-reply');
});

test('a restarted process picks up mail settings changed since boot', function () {
    $this->settings->setMany([
        'mail_mailer' => 'smtp',
        'mail_host' => 'a.example.com',
    ]);

    resolveFreshMailer();

    expect(config('mail.mailers.smtp.host'))->toBe('a.example.com');

    $this->settings->set(SettingKey::MailHost, 'b.example.com');

    // What a `queue:restart` does: the next boot resolves a fresh manager.
    resolveFreshMailer();

    expect(config('mail.mailers.smtp.host'))->toBe('b.example.com');
});
