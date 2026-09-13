<?php

use Laravel\Fortify\Features;

/*
 * The browser half of passkey login cannot be tested end to end here: real
 * WebAuthn needs a Chrome DevTools virtual authenticator, and the Pest browser
 * plugin exposes no CDP session to install one. What is testable -- and what
 * actually breaks on a dependency bump -- is the challenge the browser is
 * handed before any of that JavaScript runs. A malformed or missing challenge
 * fails passkey login for everyone, and nothing else in the suite looks at it.
 */

beforeEach(function () {
    if (! Features::enabled(Features::passkeys())) {
        $this->markTestSkipped('Passkeys are disabled.');
    }
});

test('the passkey login options endpoint issues a well formed challenge', function () {
    $response = $this->getJson(route('passkey.login-options'));

    $response->assertOk();

    // The payload is nested under "options", which is the shape the
    // @laravel/passkeys client reads; flattening it would break the browser.
    $options = $response->json('options');

    expect($options)->toHaveKeys(['challenge', 'rpId', 'userVerification'])
        ->and($options['challenge'])->toBeString()->not->toBeEmpty();
});

/**
 * The relying party id must match the host the site is served from, or every
 * browser silently refuses the credential. It is derived from APP_URL in
 * config/fortify.php, which makes it exactly the kind of value that is correct
 * locally and wrong once deployed.
 */
test('the relying party id matches the application host', function () {
    $options = $this->getJson(route('passkey.login-options'))->json('options');

    $expected = parse_url((string) config('app.url'), PHP_URL_HOST);

    expect($options['rpId'])->toBe($expected);
});
