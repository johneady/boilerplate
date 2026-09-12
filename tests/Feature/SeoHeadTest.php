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

test('a stored logo replaces the default favicon links and previews', function () {
    Storage::fake('public');

    foreach (['favicon', 'apple-touch', 'social'] as $conversion) {
        Storage::disk('public')->put("logo/abc/{$conversion}.webp", 'x');
    }

    app(Settings::class)->set(SettingKey::Logo, 'logo/abc');

    $this->get('/')
        ->assertSee('<link rel="icon" href="/storage/logo/abc/favicon.webp" type="image/webp" sizes="any" />', false)
        ->assertSee('<link rel="apple-touch-icon" href="/storage/logo/abc/apple-touch.webp" />', false)
        ->assertSee('<meta property="og:image" content="'.url('/storage/logo/abc/social.webp').'" />', false)
        ->assertSee('<meta name="twitter:card" content="summary" />', false)
        // The bundled svg gives way to the stored icon; the ico stays as the
        // always-decodable fallback.
        ->assertDontSee('/favicon.svg')
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="any" />', false);
});

test('a missing conversion falls back to the default icons rather than a broken link', function () {
    app(Settings::class)->set(SettingKey::Logo, 'logo/gone');

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml" />', false)
        ->assertDontSee('og:image');
});

/**
 * The upload is the site's brand mark, not only its favicon: x-app-logo-icon
 * renders the stored "mark" conversion wherever the bundled SVG would go, so
 * a logo uploaded from the admin panel reaches the public header and the auth
 * pages without either layout knowing the setting exists.
 */
test('a stored logo becomes the brand mark on the public page', function () {
    Storage::fake('public');
    Storage::disk('public')->put('logo/abc/mark.webp', 'x');

    app(Settings::class)->set(SettingKey::Logo, 'logo/abc');

    $this->get('/')
        ->assertSee('src="/storage/logo/abc/mark.webp"', false)
        // The bundled gradient mark gives way to it entirely.
        ->assertDontSee('app-logo-', false);
});

test('a stored logo becomes the brand mark on the auth pages', function () {
    Storage::fake('public');
    Storage::disk('public')->put('logo/abc/mark.webp', 'x');

    app(Settings::class)->set(SettingKey::Logo, 'logo/abc');

    // The split layout renders the mark twice: the backdrop lockup and the
    // narrow-viewport header above the form.
    $html = $this->get(route('login'))->assertSuccessful()->getContent();

    expect(substr_count((string) $html, 'src="/storage/logo/abc/mark.webp"'))->toBe(2);
});

test('the bundled mark renders when no logo has been uploaded', function () {
    $this->get('/')
        ->assertSuccessful()
        // The gradient is painted from the mark's own defs rather than
        // inherited, so the stops are what prove the new mark rendered.
        ->assertSee('#6366F1', false)
        ->assertDontSee('/storage/logo/', false);
});

/**
 * Every call site renders the business name as text beside the mark, so an
 * alt naming the business would make a screen reader announce the link twice
 * over ("Acme Acme"). The mark is decoration next to that name, not a second
 * copy of it.
 */
test('the uploaded mark is decorative rather than a second copy of the business name', function () {
    Storage::fake('public');
    Storage::disk('public')->put('logo/abc/mark.webp', 'x');

    app(Settings::class)->set(SettingKey::Logo, 'logo/abc');
    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');

    $html = (string) $this->get('/')->assertSuccessful()->getContent();

    expect($html)->toContain('alt=""')
        ->and($html)->not->toContain('alt="Cromulent Widgets"');
});
