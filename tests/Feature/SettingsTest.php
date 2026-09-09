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
