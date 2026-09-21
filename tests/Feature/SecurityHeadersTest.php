<?php

/**
 * Guards the nginx security response headers and, more importantly, the way
 * they are wired in.
 *
 * nginx's add_header DOES NOT MERGE. A `location` that sets any add_header of
 * its own discards every add_header inherited from the server block — so
 * /build/ and /images/, which set Cache-Control for asset caching, would
 * silently serve with no security headers at all while the site looked
 * perfectly correct. That is the regression these tests exist for: nothing in
 * the application surfaces it, and a browser does not complain about a header
 * that is merely absent.
 *
 * The headers therefore live in ONE included file rather than being repeated
 * in each block. Repetition is what rots — four directives in three places,
 * where changing one copy leaves the others stale with nothing to catch it.
 *
 * These assert the shipped config, not a live response: the headers are
 * nginx's, and nothing in the PHP request path can see them. The image build
 * runs `nginx -t`, which catches a syntax error or a missing include target,
 * so between that and these assertions both halves are covered.
 */
beforeEach(function () {
    $this->vhost = file_get_contents(base_path('docker/nginx/default.conf'));
    $this->headers = file_get_contents(base_path('docker/nginx/security-headers.conf'));
});

test('the snippet sets every security header the app relies on', function (string $header) {
    expect($this->headers)->toContain($header);
})->with([
    // Blocks MIME sniffing. config/media.php stores documents as uploaded, so
    // this is what stops a file of markup being promoted to executable HTML.
    'add_header X-Content-Type-Options "nosniff" always;',
    // Clickjacking against an authenticated Filament session.
    'add_header X-Frame-Options "SAMEORIGIN" always;',
    // Laravel's signed URLs carry their credential in the query string, which
    // a full referrer would hand to the next site the user visits.
    'add_header Referrer-Policy "strict-origin-when-cross-origin" always;',
    'add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;',
]);

test('every header is marked always, so error responses carry it too', function () {
    // Without `always`, nginx omits the header on 4xx/5xx — exactly the pages
    // that render attacker-influenced input.
    preg_match_all('/^\s*add_header\s+\S+.*$/m', $this->headers, $matches);

    expect($matches[0])->not->toBeEmpty();

    foreach ($matches[0] as $directive) {
        expect(trim($directive))->toEndWith('always;');
    }
});

test('the vhost includes the snippet at the server level', function () {
    expect($this->vhost)->toContain('include /etc/nginx/security-headers.conf;');
});

test('every location that sets its own add_header re-includes the snippet', function () {
    // THE regression guard, and the reason this file exists. A location with
    // its own add_header silently drops the inherited set; re-including is
    // what restores it. A new caching location that forgets this serves
    // unprotected and nothing reports it.
    preg_match_all('/location[^{]*\{(?:[^{}]|\{[^{}]*\})*\}/', $this->vhost, $blocks);

    $offenders = array_values(array_filter(
        $blocks[0],
        fn (string $block): bool => str_contains($block, 'add_header')
            && ! str_contains($block, 'include /etc/nginx/security-headers.conf;')
    ));

    expect($offenders)->toBe([]);
});

test('the headers are defined once, not copied into each location', function () {
    // The point of the include. A copy pasted back into the vhost is how the
    // three versions drift apart again.
    expect(substr_count($this->vhost, 'add_header X-Content-Type-Options'))->toBe(0);
});

test('the snippet is copied into the image', function () {
    // An include naming a file the image does not carry fails `nginx -t` at
    // build time, but only if the COPY is actually there to be checked.
    expect(file_get_contents(base_path('Dockerfile')))
        ->toContain('COPY docker/nginx/security-headers.conf /etc/nginx/security-headers.conf');
});
