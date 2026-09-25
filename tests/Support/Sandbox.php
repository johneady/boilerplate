<?php

namespace Tests\Support;

use App\Payments\Drivers\PayPal\PayPalClient;
use Illuminate\Support\Facades\File;
use Stripe\StripeClient;

/**
 * Credentials and helpers for the opt-in suite against the real Stripe and
 * PayPal sandboxes (tests/Sandbox).
 *
 * Credentials come from the environment, never the settings table, and a
 * Stripe key must be a test key: nothing here may ever run against a live
 * account. Every object a test creates is tidied up (archived, cancelled or
 * deleted) where the gateway allows it.
 */
class Sandbox
{
    /**
     * The address webhook endpoints are registered at: public and HTTPS, as
     * the gateways require, and deliberately not a real site.
     */
    public const string PUBLIC_ROOT = 'https://sandbox-contract.example.com';

    public static function stripeSecret(): ?string
    {
        $secret = getenv('STRIPE_SANDBOX_SECRET');

        return is_string($secret) && str_starts_with($secret, 'sk_test_') ? $secret : null;
    }

    /**
     * @return array{client_id: string, client_secret: string}|null
     */
    public static function paypal(): ?array
    {
        $id = getenv('PAYPAL_SANDBOX_CLIENT_ID');
        $secret = getenv('PAYPAL_SANDBOX_CLIENT_SECRET');

        return is_string($id) && $id !== '' && is_string($secret) && $secret !== ''
            ? ['client_id' => $id, 'client_secret' => $secret]
            : null;
    }

    /**
     * A raw Stripe client, for arranging what a customer would do on
     * Stripe's side (confirming a card) and for tidying up.
     */
    public static function stripe(): StripeClient
    {
        return new StripeClient(['api_key' => (string) static::stripeSecret(), 'stripe_version' => (string) config('payments.stripe.api_version')]);
    }

    /**
     * A raw PayPal client, for reading back what the driver created and for
     * tidying up.
     */
    public static function paypalClient(string $webhookId = ''): PayPalClient
    {
        $credentials = static::paypal() ?? ['client_id' => '', 'client_secret' => ''];

        return new PayPalClient((string) config('payments.paypal.base_urls.sandbox'), $credentials['client_id'], $credentials['client_secret'], $webhookId);
    }

    /**
     * Publish the site at a public HTTPS address (APP_URL), for webhook
     * registration.
     */
    public static function servePublicly(): void
    {
        config()->set('app.url', static::PUBLIC_ROOT);
    }

    /**
     * Save a real gateway payload beside the run, to compare with the
     * hand-written fixtures in tests/Fixtures/Payments when the API version
     * moves. Never written over the fixtures themselves: those are trimmed
     * and adjusted per test.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function capture(string $name, array $payload): void
    {
        $directory = storage_path('framework/testing/payment-captures');

        File::ensureDirectoryExists($directory);
        File::put("{$directory}/{$name}.json", (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
