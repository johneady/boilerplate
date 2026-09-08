<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/**
 * Behind Dokploy's Traefik, TLS is terminated at the proxy and the container
 * receives plain HTTP carrying X-Forwarded-Proto: https. Unless that proxy is
 * trusted, the header is ignored, Laravel reads the request as insecure, and
 * every url(), asset() and @vite URL comes out as http:// on an https:// page
 * — which the browser blocks as mixed content, taking the stylesheet, the JS
 * bundle and Flux's script with it.
 *
 * docker-compose.dokploy.yml has always set TRUST_PROXIES=*, but nothing read
 * it, so the variable was inert and the deploy served blocked assets. The
 * TrustProxies middleware is already in Laravel's default global stack; what
 * was missing is the config key it reads. These tests pin that key's parsing
 * and the secure-request behaviour that depends on it.
 */
function handleThroughTrustProxies(Request $request): bool
{
    $secure = false;

    (new TrustProxies)->handle($request, function (Request $request) use (&$secure) {
        $secure = $request->isSecure();

        return response();
    });

    return $secure;
}

function forwardedHttpsRequest(string $remoteAddress = '10.0.1.5'): Request
{
    return Request::create('http://example.test/', server: [
        'REMOTE_ADDR' => $remoteAddress,
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
    ]);
}

test('the config key the middleware reads exists', function () {
    // TrustProxies resolves config('trustedproxy.proxies'), not a key under
    // app.*. A correct value in the wrong file is exactly as inert as the
    // missing file was.
    expect(config()->has('trustedproxy.proxies'))->toBeTrue();
});

test('a forwarded https request is treated as secure when the proxy is trusted', function () {
    config()->set('trustedproxy.proxies', '*');

    expect(handleThroughTrustProxies(forwardedHttpsRequest()))->toBeTrue();
});

test('a forwarded https request stays insecure when no proxy is trusted', function () {
    // The regression this guards: config('trustedproxy.proxies') resolving to
    // null is what produced http:// asset URLs on the https:// deploy.
    config()->set('trustedproxy.proxies', null);

    expect(handleThroughTrustProxies(forwardedHttpsRequest()))->toBeFalse();
});

test('a listed proxy is trusted and an unlisted one is not', function () {
    config()->set('trustedproxy.proxies', ['10.0.1.5', '10.0.1.6']);

    expect(handleThroughTrustProxies(forwardedHttpsRequest('10.0.1.6')))->toBeTrue()
        ->and(handleThroughTrustProxies(forwardedHttpsRequest('198.51.100.7')))->toBeFalse();
});

test('TRUST_PROXIES parses into the shape the middleware expects', function (?string $value, mixed $expected) {
    // Symfony matches trusted proxies per entry, so a comma-separated list has
    // to become an array — handing it the raw "10.0.0.1,10.0.0.2" string would
    // match neither address. "*" must stay a bare string.
    $resolve = function (?string $raw): mixed {
        return match ($raw) {
            null, '' => null,
            '*' => '*',
            default => array_values(array_filter(array_map('trim', explode(',', $raw)))),
        };
    };

    expect($resolve($value))->toBe($expected);
})->with([
    'unset' => [null, null],
    'empty' => ['', null],
    'wildcard' => ['*', '*'],
    'single address' => ['10.0.1.5', ['10.0.1.5']],
    'padded list' => ['10.0.1.5, 10.0.1.6', ['10.0.1.5', '10.0.1.6']],
]);

test('asset URLs are generated as https behind a trusted proxy', function () {
    // The user-visible symptom, asserted on the URLs the page actually emits:
    // asset() and url() build their scheme from the request, so an untrusted
    // proxy makes every /build/assets/*.css and *.js link http:// on an
    // https:// page and Chrome blocks it outright.
    config()->set('trustedproxy.proxies', '*');

    $this->withServerVariables([
        'REMOTE_ADDR' => '10.0.1.5',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'example.test',
    ])->get('/');

    expect(asset('build/assets/app.css'))->toStartWith('https://example.test/')
        ->and(url('/'))->toStartWith('https://example.test');
});

test('asset URLs fall back to plain http when the proxy is untrusted', function () {
    // The regression itself. Without this the previous test would still pass
    // on a codebase that ignores X-Forwarded-Proto entirely, because nothing
    // would prove the trusted config is what changed the scheme.
    config()->set('trustedproxy.proxies', null);

    $this->withServerVariables([
        'REMOTE_ADDR' => '10.0.1.5',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'example.test',
    ])->get('/');

    expect(asset('build/assets/app.css'))->toStartWith('http://');
});
