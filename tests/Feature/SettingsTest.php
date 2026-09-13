<?php

use App\Models\Setting;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

test('unsaved seo settings read as indexable with no title or description', function () {
    // The page head omits the tags for blank values and falls back to the
    // business name, so a fresh install with no rows saved must read the
    // declared defaults rather than nulls the views would have to guard.
    expect($this->settings->string(SettingKey::SeoTitle))->toBe('')
        ->and($this->settings->string(SettingKey::SeoDescription))->toBe('')
        ->and($this->settings->string(SettingKey::Logo))->toBe('')
        ->and($this->settings->boolean(SettingKey::AllowSearchIndexing))->toBeTrue();
});

test('a stored indexing value that is not recognisably true reads as false', function (mixed $stored) {
    // Indexing is a gate like registration: a garbage row must fail closed
    // to noindex rather than publish a site the operator meant to hide.
    Setting::create(['key' => 'allow_search_indexing', 'value' => $stored]);

    expect($this->settings->boolean(SettingKey::AllowSearchIndexing))->toBeFalse();
})->with([
    'the string false' => ['false'],
    'the string off' => ['off'],
    'the string zero' => ['0'],
    'an empty string' => [''],
    'null' => [null],
]);

test('a blank stored seo title or description reads as the empty string', function (mixed $stored) {
    app(Settings::class)->set(SettingKey::SeoTitle, $stored);
    app(Settings::class)->set(SettingKey::SeoDescription, $stored);

    app()->forgetScopedInstances();

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::SeoTitle))->toBe('')
        ->and($settings->string(SettingKey::SeoDescription))->toBe('');
})->with([
    'empty string' => [''],
    'whitespace' => ['   '],
    'null' => [null],
    'an array' => [['nope']],
]);

test('the site icon url resolves only once the conversions exist', function () {
    Storage::fake('public');

    expect($this->settings->logoUrl('favicon'))->toBeNull();

    app(Settings::class)->set(SettingKey::Logo, 'logo/abc');

    // Resolution is memoised per instance (the head asks three times per
    // page), so stand in for the next request's fresh instance at each step.
    app()->forgetScopedInstances();

    // A row pointing at a directory the processing job has not written yet
    // resolves to null, so the head falls back to the bundled favicon files
    // rather than linking at a file that does not exist.
    expect(app(Settings::class)->logoUrl('favicon'))->toBeNull();

    Storage::disk('public')->put('logo/abc/favicon.webp', 'x');

    app()->forgetScopedInstances();

    expect(app(Settings::class)->logoUrl('favicon'))->toBe('/storage/logo/abc/favicon.webp');
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

test('an unreachable database reads settings as their defaults', function () {
    // The global View::composer('*') resolves the business name for EVERY
    // view, so a settings read that throws on a connection failure takes the
    // error pages down with it -- a 500 caused by the database would throw
    // again inside the 500 page. See resources/views/errors.
    //
    // The outage is simulated by pointing the DEFAULT at a throwaway
    // connection that cannot connect, rather than by breaking the connection
    // the suite is using: RefreshDatabase holds a transaction on that one,
    // and purging or reconnecting it destroys the shared in-memory database
    // for every later test in the process.
    $default = config('database.default');

    config([
        'database.connections.unreachable_test' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'nothing_here',
            'username' => 'nobody',
            'password' => '',
        ],
        'database.default' => 'unreachable_test',
    ]);

    // Restored before the test ends: RefreshDatabase resolves the connection
    // it rolls back at teardown from database.default, so leaving the bogus
    // one in place makes it roll back the wrong connection and every later
    // test in the process fails with "cannot start a transaction within a
    // transaction".
    $restore = fn () => config(['database.default' => $default]);

    app()->forgetScopedInstances();

    try {
        expect(app(Settings::class)->businessName())->toBe(config('app.name'))
            ->and(app(Settings::class)->boolean(SettingKey::AllowRegistration))
            ->toBe(SettingKey::AllowRegistration->default());
    } finally {
        $restore();
        app()->forgetScopedInstances();
    }
});

test('a missing settings table still fails loudly', function () {
    // Degrading to defaults is for an outage, not for un-run migrations:
    // silently serving defaults there would hide a broken deploy.
    //
    // Queried against a table that does not exist rather than dropping the
    // real one: DDL is not transactional on MySQL/MariaDB, so a drop escapes
    // RefreshDatabase's rollback and every later test loses the table.
    $missing = new class extends Setting
    {
        protected $table = 'settings_that_do_not_exist';
    };

    expect(fn () => $missing->newQuery()->pluck('value', 'key'))
        ->toThrow(QueryException::class);
});

test('unsaved locale and time settings read as UTC, English and the default formats', function () {
    expect($this->settings->string(SettingKey::Timezone))->toBe('UTC')
        ->and($this->settings->string(SettingKey::Locale))->toBe('en')
        ->and($this->settings->string(SettingKey::DateFormat))->toBe('j M Y')
        ->and($this->settings->string(SettingKey::TimeFormat))->toBe('H:i');
});

test('a stored timezone that is not a real identifier reads as UTC', function (mixed $stored) {
    // Every date the application renders flows through this setting, so a
    // row hand-edited to a typo must fail closed rather than hand Carbon an
    // identifier it throws on at render time.
    Setting::create(['key' => 'timezone', 'value' => $stored]);

    expect($this->settings->string(SettingKey::Timezone))->toBe('UTC');
})->with([
    'a typo' => ['Australia/Sydny'],
    'a made-up zone' => ['Mars/Olympus_Mons'],
    'an offset instead of a zone' => ['+10:00'],
    'an empty string' => [''],
    'a boolean true' => [true],
    'null' => [null],
]);

test('a stored date or time format that is not one of the presets reads as the default', function (mixed $stored) {
    Setting::create(['key' => 'date_format', 'value' => $stored]);
    Setting::create(['key' => 'time_format', 'value' => $stored]);

    expect($this->settings->string(SettingKey::DateFormat))->toBe('j M Y')
        ->and($this->settings->string(SettingKey::TimeFormat))->toBe('H:i');
})->with([
    'a format that is not offered' => ['d/m/y'],
    'a format string from another convention' => ['DD/MM/YYYY'],
    'an empty string' => [''],
    'a boolean true' => [true],
    'null' => [null],
]);

test('a stored locale that is not offered reads as English', function (mixed $stored) {
    Setting::create(['key' => 'locale', 'value' => $stored]);

    expect($this->settings->string(SettingKey::Locale))->toBe('en');
})->with([
    'a language without translations shipped' => ['tlh'],
    'an English name' => ['english'],
    'an empty string' => [''],
    'a boolean true' => [true],
    'null' => [null],
]);

test('dates are formatted through the timezone and formats in force', function () {
    app(Settings::class)->setMany([
        'timezone' => 'Australia/Sydney',
        'date_format' => 'd/m/Y',
        'time_format' => 'H:i',
    ]);

    app()->forgetScopedInstances();

    $settings = app(Settings::class);

    // Parsed as UTC, the storage timezone: 00:30 UTC is 11:30 in Sydney on
    // a January day, which is the conversion an administrator expects to
    // see rather than the UTC wall clock the database holds.
    $instant = CarbonImmutable::parse('2026-01-15 00:30:00');

    expect($settings->formatDateTime($instant))->toBe('15/01/2026, 11:30')
        ->and($settings->formatDate($instant))->toBe('15/01/2026')
        ->and($settings->formatTime($instant))->toBe('11:30');
});

test('month and day names follow the locale setting', function () {
    app(Settings::class)->setMany([
        'locale' => 'fr',
        'date_format' => 'j F Y',
    ]);

    app()->forgetScopedInstances();

    expect(app(Settings::class)->formatDate(CarbonImmutable::parse('2026-01-15 00:30:00')))
        ->toBe('15 janvier 2026');
});

test('relative times follow the locale setting', function () {
    app(Settings::class)->set(SettingKey::Locale, 'fr');

    app()->forgetScopedInstances();

    $diff = app(Settings::class)->formatRelative(CarbonImmutable::now()->subHours(2));

    expect($diff)->toContain('heures')
        ->and($diff)->not->toContain('hours');
});

test('a null date formats as the empty string', function () {
    expect($this->settings->formatDate(null))->toBe('')
        ->and($this->settings->formatTime(null))->toBe('')
        ->and($this->settings->formatDateTime(null))->toBe('')
        ->and($this->settings->formatRelative(null))->toBe('');
});
