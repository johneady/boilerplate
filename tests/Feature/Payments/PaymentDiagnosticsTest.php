<?php

use App\Models\PlanPrice;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\PaymentDiagnostics;
use App\Settings\DiagnosticSeverity;
use Tests\Support\Payments;

/**
 * The failing checks, by name => severity.
 *
 * @return array<string, DiagnosticSeverity>
 */
function paymentFailures(): array
{
    return collect(app(PaymentDiagnostics::class)->failures())
        ->mapWithKeys(fn ($result): array => [$result->name => $result->severity])
        ->all();
}

test('nothing is reported while payments are off and nothing is stored', function () {
    expect(paymentFailures())->toBe([]);
});

test('payments switched on with no gateway customers can use is an error', function () {
    Payments::enable(['demo_gateway_enabled' => false]);

    expect(paymentFailures())->toBe(['Payment gateways' => DiagnosticSeverity::Error]);
});

test('an enabled gateway without credentials for the current mode is an error', function () {
    Payments::enable(['stripe_enabled' => true]);

    expect(paymentFailures())->toHaveKey('Stripe credentials', DiagnosticSeverity::Error)
        ->toHaveKey('Stripe webhooks', DiagnosticSeverity::Warning);
});

test('a test key saved as the live Stripe key is an error', function () {
    Payments::enable([
        'payments_mode' => 'live',
        'stripe_enabled' => true,
        'stripe_live_secret_key' => 'sk_test_51Oops',
        'stripe_live_webhook_secret' => 'whsec_x',
    ]);

    expect(paymentFailures())->toHaveKey('Stripe live key', DiagnosticSeverity::Error);
});

test('sandbox mode on a production site is a warning', function () {
    Payments::enable();
    config()->set('app.env', 'production');

    expect(paymentFailures())->toHaveKey('Payment mode', DiagnosticSeverity::Warning);
});

test('a fully configured gateway passes every check', function () {
    Payments::enable([
        'payments_mode' => 'live',
        'stripe_enabled' => true,
        'stripe_live_secret_key' => 'sk_live_51Fine',
        'stripe_live_webhook_secret' => 'whsec_fine',
    ]);
    config()->set('app.env', 'production');

    expect(paymentFailures())->toBe([]);
});

test('a plan on offer that a gateway does not have yet is a warning', function () {
    $price = PlanPrice::factory()->create();
    Payments::enable([
        'demo_gateway_enabled' => false,
        'stripe_enabled' => true,
        'stripe_sandbox_secret_key' => 'sk_test_51Example',
        'stripe_sandbox_webhook_secret' => 'whsec_x',
    ]);

    expect(paymentFailures())->toBe(['Subscription plans synced' => DiagnosticSeverity::Warning]);

    $price->recordGatewayRef(Gateway::Stripe, GatewayMode::Sandbox, ['id' => 'price_x', 'signature' => 'active']);
    $price->plan->recordGatewayRef(Gateway::Stripe, GatewayMode::Sandbox, 'prod_x');

    expect(paymentFailures())->toBe([]);
});
