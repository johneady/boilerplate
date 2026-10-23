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
     * The moment the fixtures were recorded, after every created/create_time
     * in them and before the subscription periods they report run out
     * (Stripe 2026-10-21, PayPal 2026-10-23).
     *
     * Their timestamps are absolute, so a test that compares one with now()
     * must travel here first. On the real clock it passes only until that
     * date goes by, then fails for good.
     */
    public const string RECORDED_AT = '2026-09-24 12:00:00';

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
