<?php

use App\Models\Setting;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Storage;

// The icon seeding writes to both image disks; faking them keeps each test
// isolated and stops generated files leaking into the real storage tree.
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

test('it seeds the demo business details', function () {
    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::BusinessAddress))->toBe("123 Example Street\nAnytown, ST 12345")
        ->and($settings->string(SettingKey::BusinessPhone))->toBe('+1 (555) 123-4567')
        ->and($settings->string(SettingKey::BusinessEmail))->toBe('hello@example.com');
});

test('it seeds the demo seo copy', function () {
    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::SeoTitle))->toBe('Cromulent Widgets')
        ->and($settings->string(SettingKey::SeoDescription))->toBe('Quality example widgets, made and shipped from Anytown. Replace this text from the admin panel\'s SEO & brand settings.');
});

test('it seeds a placeholder site icon through the processing pipeline', function () {
    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    $directory = app(Settings::class)->string(SettingKey::SiteIcon);

    // Exactly what an administrator's upload would have produced: a stored
    // directory plus the re-encoded conversions on the public disk, and no
    // unprocessed original left behind on the private one.
    expect($directory)->toStartWith('site-icon/')
        ->and(Storage::disk('public')->exists($directory.'/favicon.webp'))->toBeTrue()
        ->and(Storage::disk('public')->exists($directory.'/apple-touch.webp'))->toBeTrue()
        ->and(Storage::disk('public')->exists($directory.'/social.webp'))->toBeTrue()
        ->and(Storage::disk('local')->allFiles('uploads/pending'))->toBeEmpty();
});

test('re-seeding does not overwrite an operator\'s own seo copy or icon', function () {
    app(Settings::class)->setMany([
        'seo_description' => 'Our real description.',
        'site_icon' => 'site-icon/mine',
    ]);

    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::SeoDescription))->toBe('Our real description.')
        ->and($settings->string(SettingKey::SiteIcon))->toBe('site-icon/mine')
        // The details never filled in are still created.
        ->and($settings->string(SettingKey::SeoTitle))->toBe('Cromulent Widgets');
});

test('a failure while processing the placeholder icon does not fail seeding', function () {
    // An empty conversion set makes the job throw. Seeding re-runs on every
    // deploy, so an icon that will not generate must not crashloop the
    // container over a decoration -- the rest of the seed still lands.
    config(['images.conversions.site-icon' => []]);

    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::SiteIcon))->toBe('')
        ->and($settings->string(SettingKey::SeoTitle))->toBe('Cromulent Widgets')
        ->and($settings->string(SettingKey::BusinessEmail))->toBe('hello@example.com');
});

test('it does not seed the business name over its declared default', function () {
    $this->seed(SettingsSeeder::class);

    expect(Setting::where('key', SettingKey::BusinessName->value)->exists())->toBeFalse();
});

test('re-seeding does not overwrite an operator\'s own details', function () {
    app(Settings::class)->set(SettingKey::BusinessAddress, '2 Real Road');

    $this->seed(SettingsSeeder::class);

    app()->forgetInstance(Settings::class);

    // The edited row survives, while the details never filled in are created.
    expect(app(Settings::class)->string(SettingKey::BusinessAddress))->toBe('2 Real Road')
        ->and(app(Settings::class)->string(SettingKey::BusinessPhone))->toBe('+1 (555) 123-4567');
});

test('the full seed includes the demo business details', function () {
    $this->seed(DatabaseSeeder::class);

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->string(SettingKey::BusinessEmail))->toBe('hello@example.com');
});

test('the demo details are seeded without the factory', function () {
    // The production image is installed with --no-dev, so fakerphp/faker is
    // absent and any factory call in this seeder is a fatal error there.
    // See .ai/rules/seeders.md.
    $source = file_get_contents(base_path('database/seeders/SettingsSeeder.php'));

    expect($source)->not->toContain('factory()');
});
