<?php

use App\Settings\SettingKey;
use App\Settings\Settings;

test('robots.txt invites crawlers and points at the sitemap', function () {
    $response = $this->get('/robots.txt')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    $lines = array_values(array_filter(explode("\n", trim((string) $response->getContent()))));

    expect($lines)->toBe([
        'User-agent: *',
        'Disallow:',
        'Sitemap: '.route('sitemap'),
    ]);
});

/**
 * The static public/robots.txt this route replaced could not see the setting,
 * so an administrator turning indexing off got a noindex meta tag and a
 * robots.txt still inviting crawlers over the whole site.
 */
test('turning off indexing disallows crawling in robots.txt', function () {
    app(Settings::class)->set(SettingKey::AllowSearchIndexing, false);

    $content = (string) $this->get('/robots.txt')->assertSuccessful()->getContent();

    // Asserted as whole lines: "Disallow:" is a substring of "Disallow: /",
    // so a substring check cannot tell the two directives apart.
    expect(array_values(array_filter(explode("\n", trim($content)))))
        ->toBe(['User-agent: *', 'Disallow: /'])
        ->and($content)->not->toContain('Sitemap:');
});

/**
 * A real file in public/ is matched by the web server before the request ever
 * reaches PHP, which would silently restore the mismatch above.
 */
test('no static robots.txt shadows the route', function () {
    expect(file_exists(public_path('robots.txt')))->toBeFalse();
});

test('the sitemap lists the public pages as valid xml', function () {
    $response = $this->get('/sitemap.xml')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/xml');

    $content = (string) $response->getContent();

    expect($content)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->toContain('<loc>'.route('home').'</loc>');

    $xml = simplexml_load_string($content);

    expect($xml)->not->toBeFalse()
        ->and($xml->url)->toHaveCount(1);
});

test('the sitemap is empty while indexing is off', function () {
    app(Settings::class)->set(SettingKey::AllowSearchIndexing, false);

    $content = (string) $this->get('/sitemap.xml')->assertSuccessful()->getContent();

    expect($content)->toContain('<urlset')
        ->not->toContain('<loc>');

    expect(simplexml_load_string($content))->not->toBeFalse();
});

test('the head carries organization structured data', function () {
    app(Settings::class)->setMany([
        'business_name' => 'Cromulent Widgets',
        'business_email' => 'hello@example.com',
        'business_phone' => '+44 20 7946 0000',
        'business_address' => '1 Example Street, London',
        'seo_description' => 'Purveyors of fine example widgets.',
    ]);

    $html = (string) $this->get('/')->assertSuccessful()->getContent();

    expect($html)->toContain('<script type="application/ld+json">');

    $json = json_decode(
        (string) preg_replace('/.*<script type="application\/ld\+json">(.*?)<\/script>.*/s', '$1', $html),
        true,
    );

    expect($json)->toMatchArray([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Cromulent Widgets',
        'url' => url('/'),
        'description' => 'Purveyors of fine example widgets.',
        'email' => 'hello@example.com',
        'telephone' => '+44 20 7946 0000',
    ])->and($json['address'])->toMatchArray([
        '@type' => 'PostalAddress',
        'streetAddress' => '1 Example Street, London',
    ]);
});

/**
 * A blank telephone is a worse claim than no telephone: an unset detail must
 * be absent from the schema rather than present and empty.
 */
test('unset business details are omitted from the structured data', function () {
    $html = (string) $this->get('/')->assertSuccessful()->getContent();

    $json = json_decode(
        (string) preg_replace('/.*<script type="application\/ld\+json">(.*?)<\/script>.*/s', '$1', $html),
        true,
    );

    expect($json)->not->toHaveKeys(['email', 'telephone', 'address', 'description', 'logo']);
});

/**
 * The structured data is inlined into a script element, so a quote or an angle
 * bracket in a stored name must not be able to close it early.
 */
test('a business name containing markup cannot break out of the script block', function () {
    app(Settings::class)->set(SettingKey::BusinessName, '</script><script>alert(1)</script>');

    $html = (string) $this->get('/')->assertSuccessful()->getContent();

    $block = (string) preg_replace('/.*<script type="application\/ld\+json">(.*?)<\/script>.*/s', '$1', $html);

    // The injected markup must survive only in escaped form, and the JSON must
    // still parse back to the name verbatim -- escaping that mangled the value
    // would be a different bug wearing the same green test.
    expect($block)->not->toContain('<script')
        ->and($block)->toContain('\u003C')
        ->and(json_decode($block, true)['name'])->toBe('</script><script>alert(1)</script>');
});

test('structured data is omitted while indexing is off', function () {
    app(Settings::class)->set(SettingKey::AllowSearchIndexing, false);

    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('application/ld+json', false);
});

/**
 * Every indexing signal in the head has to agree. With indexing off the
 * sitemap is served empty, so advertising it beside a noindex directive would
 * point a crawler at nothing.
 */
test('the sitemap link follows the indexing setting', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('rel="sitemap"', false);

    app(Settings::class)->set(SettingKey::AllowSearchIndexing, false);

    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('rel="sitemap"', false);
});
