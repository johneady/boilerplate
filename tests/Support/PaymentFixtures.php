<?php

namespace Tests\Support;

/**
 * Gateway API responses and webhook events recorded as JSON fixtures.
 *
 * Shapes follow the real Stripe and PayPal payloads; the sandbox suite is what
 * keeps them honest. Tests adjust a fixture with $overrides rather than
 * keeping a near-copy per case.
 */
class PaymentFixtures
{
    /**
     * @param  array<string, mixed>  $overrides  Merged recursively over the fixture.
     * @return array<string, mixed>
     */
    public static function load(string $name, array $overrides = []): array
    {
        /** @var array<string, mixed> $fixture */
        $fixture = json_decode((string) file_get_contents(base_path("tests/Fixtures/Payments/{$name}.json")), true, flags: JSON_THROW_ON_ERROR);

        return array_replace_recursive($fixture, $overrides);
    }

    /**
     * A Stripe-Signature header for a payload, as Stripe computes it.
     */
    public static function stripeSignature(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }
}
