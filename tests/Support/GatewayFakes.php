<?php

namespace Tests\Support;

use Closure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Fake Stripe and PayPal APIs, answering by method and path, and webhook
 * deliveries signed the way each gateway signs them.
 *
 * Every route a test needs is registered in one call: Http::fake() stubs
 * accumulate and the first match wins, so a second call would never be
 * reached. An unexpected request answers 500, which the drivers treat as the
 * gateway being unavailable, so it surfaces as a failure.
 */
class GatewayFakes
{
    /**
     * @param  array<string, array<string, mixed>|Closure(Request): mixed>  $routes  Keyed "METHOD /v1/path".
     */
    public static function stripe(array $routes): void
    {
        Http::fake(fn (Request $request) => static::answer($routes, $request, fn (string $key) => Http::response(['error' => ['message' => "Unexpected {$key}"]], 500)));
    }

    /**
     * The OAuth token endpoint is always answered.
     *
     * @param  array<string, array<string, mixed>|Closure(Request): mixed>  $routes
     */
    public static function paypal(array $routes): void
    {
        $routes = ['POST /v1/oauth2/token' => PaymentFixtures::load('paypal/token'), ...$routes];

        Http::fake(fn (Request $request) => static::answer($routes, $request, fn (string $key) => Http::response(['name' => 'UNEXPECTED', 'message' => "Unexpected {$key}"], 500)));
    }

    /**
     * A Stripe request's form body, decoded.
     *
     * @return array<string, mixed>
     */
    public static function stripeBody(Request $request): array
    {
        parse_str($request->body(), $body);

        return $body;
    }

    /**
     * Whether a request went to this method and path.
     */
    public static function is(Request $request, string $route): bool
    {
        return $request->method().' '.parse_url($request->url(), PHP_URL_PATH) === $route;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function deliverStripe(array $event, string $secret = 'whsec_example'): TestResponse
    {
        $payload = (string) json_encode($event);

        return test()->call('POST', route('payments.webhook', ['gateway' => 'stripe', 'mode' => 'sandbox']), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => PaymentFixtures::stripeSignature($payload, $secret),
        ], $payload);
    }

    /**
     * PayPal's signature is verified by PayPal's API, which the test fakes;
     * these are the headers a delivery carries.
     *
     * @param  array<string, mixed>  $event
     */
    public static function deliverPayPal(array $event): TestResponse
    {
        return test()->call('POST', route('payments.webhook', ['gateway' => 'paypal', 'mode' => 'sandbox']), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-360caa42',
            'HTTP_PAYPAL_TRANSMISSION_ID' => '69cd13f0-d67a-11e5-baa3-778b53f4ae55',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'c2lnbmF0dXJl',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601ZuluString(),
        ], (string) json_encode($event));
    }

    /**
     * @param  array<string, array<string, mixed>|Closure(Request): mixed>  $routes
     * @param  Closure(string): mixed  $unexpected
     */
    private static function answer(array $routes, Request $request, Closure $unexpected): mixed
    {
        $key = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);

        if (! array_key_exists($key, $routes)) {
            return $unexpected($key);
        }

        $route = $routes[$key];

        return $route instanceof Closure ? $route($request) : Http::response($route);
    }
}
