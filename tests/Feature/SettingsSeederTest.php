<?php

use App\Models\Setting;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Storage;

// Faked so a stray write during seeding cannot leak into the real storage
// tree, and each test starts from an empty disk.
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

test('it seeds the demo business details', function () {
    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::BusinessAddress))->toBe("Montréal, QC\nCanada")
        ->and($settings->string(SettingKey::BusinessEmail))->toBe('hello@sillage.example')
        ->and($settings->boolean(SettingKey::AllowRegistration))->toBeTrue();
});

test('it seeds the demo seo copy', function () {
    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::SeoTitle))->toBe('Sillage — the open perfume database')
        ->and($settings->string(SettingKey::SeoDescription))->toStartWith('Search thousands of perfumes');
});

test('it seeds the demo display timezone', function () {
    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    // A named zone rather than the bare UTC default: the demo business is
    // in Montréal, and a regional identifier shows what the setting does.
    expect(app(Settings::class)->string(SettingKey::Timezone))->toBe('America/Toronto');
});

test('re-seeding does not overwrite an operator\'s own timezone', function () {
    app(Settings::class)->set(SettingKey::Timezone, 'Australia/Sydney');

    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->string(SettingKey::Timezone))->toBe('Australia/Sydney');
});

test('it seeds no logo, leaving the bundled mark in use', function () {
    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    // The bundled x-app-logo-icon is a designed mark, so a seeded placeholder
    // would only replace it with something worse. An absent row is what makes
    // the component fall through to it.
    expect(Setting::where('key', SettingKey::Logo->value)->exists())->toBeFalse()
        ->and(app(Settings::class)->logoUrl('mark'))->toBeNull();
});

test('re-seeding does not overwrite an operator\'s own seo copy or logo', function () {
    app(Settings::class)->setMany([
        'seo_description' => 'Our real description.',
        'logo' => 'logo/mine',
    ]);

    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::SeoDescription))->toBe('Our real description.')
        ->and($settings->string(SettingKey::Logo))->toBe('logo/mine')
        // The details never filled in are still created.
        ->and($settings->string(SettingKey::SeoTitle))->toBe('Sillage — the open perfume database');
});

test('it seeds the demo business name', function () {
    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->businessName())->toBe('Sillage');
});

test('re-seeding does not overwrite an operator\'s own details', function () {
    app(Settings::class)->set(SettingKey::BusinessAddress, '2 Real Road');

    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    // The edited row survives, while the details never filled in are created.
    expect(app(Settings::class)->string(SettingKey::BusinessAddress))->toBe('2 Real Road')
        ->and(app(Settings::class)->string(SettingKey::BusinessEmail))->toBe('hello@sillage.example');
});

test('the full seed includes the demo business details', function () {
    $this->seed(DatabaseSeeder::class);

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->string(SettingKey::BusinessEmail))->toBe('hello@sillage.example');
});

test('the demo details are seeded without the factory', function () {
    // The production image is installed with --no-dev, so fakerphp/faker is
    // absent and any factory call in this seeder is a fatal error there.
    // See .ai/rules/seeders.md.
    $source = file_get_contents(base_path('database/seeders/SettingsSeeder.php'));

    expect($source)->not->toContain('factory()');
});
