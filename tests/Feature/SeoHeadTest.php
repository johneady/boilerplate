<?php

use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Support\Facades\Storage;

test('the head carries the site-wide meta tags', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('<link rel="canonical" href="'.url('/').'" />', false)
        ->assertSee('<meta property="og:type" content="website" />', false)
        ->assertSee('<meta property="og:site_name" content="'.config('app.name').'" />', false)
        ->assertSee('<meta property="og:title" content="'.config('app.name').'" />', false)
        ->assertSee('<meta property="og:url" content="'.url('/').'" />', false)
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="any" />', false)
        ->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml" />', false)
        ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png" />', false)
        ->assertDontSee('noindex')
        ->assertDontSee('name="description"')
        ->assertDontSee('og:image');
});

test('the stored seo title and description drive the title and meta tags', function () {
    app(Settings::class)->setMany([
        'seo_title' => 'Cromulent Widgets',
        'seo_description' => 'Purveyors of fine example widgets.',
    ]);

    $this->get('/')
        ->assertSee('<title>Cromulent Widgets</title>', false)
        ->assertSee('<meta name="description" content="Purveyors of fine example widgets." />', false)
        ->assertSee('<meta property="og:title" content="Cromulent Widgets" />', false)
        ->assertSee('<meta property="og:description" content="Purveyors of fine example widgets." />', false);
});

test('a blank seo title falls back to the business name', function () {
    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');

    $this->get('/')
        ->assertSee('<title>Cromulent Widgets</title>', false)
        ->assertSee('<meta property="og:title" content="Cromulent Widgets" />', false);
});

test('pages with their own title keep the suffix pattern', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')
        ->assertSuccessful()
        ->assertSee('<title>Dashboard - '.config('app.name').'</title>', false)
        ->assertSee('<meta property="og:title" content="Dashboard - '.config('app.name').'" />', false);
});

test('turning off indexing adds the noindex directive', function () {
    app(Settings::class)->set(SettingKey::AllowSearchIndexing, false);

    $this->get('/')
        ->assertSee('<meta name="robots" content="noindex, nofollow" />', false);
});

test('a stored site icon replaces the default favicon links and previews', function () {
    Storage::fake('public');

    foreach (['favicon', 'apple-touch', 'social'] as $conversion) {
        Storage::disk('public')->put("site-icon/abc/{$conversion}.webp", 'x');
    }

    app(Settings::class)->set(SettingKey::SiteIcon, 'site-icon/abc');

    $this->get('/')
        ->assertSee('<link rel="icon" href="/storage/site-icon/abc/favicon.webp" type="image/webp" sizes="any" />', false)
        ->assertSee('<link rel="apple-touch-icon" href="/storage/site-icon/abc/apple-touch.webp" />', false)
        ->assertSee('<meta property="og:image" content="'.url('/storage/site-icon/abc/social.webp').'" />', false)
        ->assertSee('<meta name="twitter:card" content="summary" />', false)
        // The bundled svg gives way to the stored icon; the ico stays as the
        // always-decodable fallback.
        ->assertDontSee('/favicon.svg')
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="any" />', false);
});

test('a missing conversion falls back to the default icons rather than a broken link', function () {
    app(Settings::class)->set(SettingKey::SiteIcon, 'site-icon/gone');

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml" />', false)
        ->assertDontSee('og:image');
});
