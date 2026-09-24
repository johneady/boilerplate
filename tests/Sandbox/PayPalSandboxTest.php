<?php

use App\Jobs\SyncPlans;
use App\Models\PaymentLink;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\TaxRate;
use App\Models\User;
use App\Payments\Actions\ReconcileSubscription;
use App\Payments\Actions\RegisterWebhooks;
use App\Payments\Actions\SyncPlan;
use App\Payments\Data\GatewayStatus;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\InvalidWebhook;
use App\Payments\PaymentManager;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Payments;
use Tests\Support\Sandbox;

/*
 * The PayPal driver against the real PayPal sandbox. Run with the Sandbox
 * test suite and PAYPAL_SANDBOX_CLIENT_ID / PAYPAL_SANDBOX_CLIENT_SECRET set.
 *
 * What needs a buyer to click Approve in PayPal's own pages -- capturing an
 * order, activating, suspending and re-activating a subscription -- cannot be
 * automated here; the README's sandbox checklist covers it by hand.
 */

beforeEach(function () {
    $credentials = Sandbox::paypal() ?? $this->markTestSkipped('Set PAYPAL_SANDBOX_CLIENT_ID and PAYPAL_SANDBOX_CLIENT_SECRET to run the PayPal sandbox tests.');

    Http::allowStrayRequests();
    Notification::fake();
    Queue::fake([SyncPlans::class]);

    Payments::enable([
        'demo_gateway_enabled' => false,
        'paypal_enabled' => true,
        'paypal_sandbox_client_id' => $credentials['client_id'],
        'paypal_sandbox_client_secret' => $credentials['client_secret'],
    ]);
});

test('a checkout creates a real order awaiting the buyer, with our tax breakdown', function () {
    TaxRate::factory()->rate('HST', '13')->create();
    $payment = Payments::checkout(PaymentLink::factory()->costing(2500)->taxable()->create(['title' => 'Sandbox contract']), Gateway::PayPal);

    $order = Sandbox::paypalClient()->get("/v2/checkout/orders/{$payment->gateway_checkout_id}");
    Sandbox::capture('paypal-order', $order);

    expect($payment->checkout_url)->toContain('paypal.com')
        ->and(app(PaymentManager::class)->driverFor($payment)->fetch($payment)->status)->toBe(GatewayStatus::Open)
        ->and($order['purchase_units'][0]['amount']['value'])->toBe('28.25')
        ->and($order['purchase_units'][0]['amount']['breakdown']['tax_total']['value'])->toBe('3.25');
});

test('a plan syncs to a real product and billing plan with its trial and tax, and a subscription on it awaits approval', function () {
    TaxRate::factory()->rate('HST', '13')->create();
    $price = PlanPrice::factory()->for(Plan::factory()->trial(7)->state(['name' => 'Sandbox contract '.Str::random(6)]))->create();
    app(SyncPlan::class)->handle($price->plan, Gateway::PayPal, GatewayMode::Sandbox);

    $planId = $price->fresh()->gatewayRef(Gateway::PayPal, GatewayMode::Sandbox)['id'];

    try {
        $plan = Sandbox::paypalClient()->get("/v1/billing/plans/{$planId}");
        Sandbox::capture('paypal-billing-plan', $plan);

        expect($plan['status'])->toBe('ACTIVE')
            ->and((float) $plan['taxes']['percentage'])->toBe(13.0)
            ->and($plan['billing_cycles'])->toHaveCount(2)
            ->and($plan['billing_cycles'][0]['tenure_type'])->toBe('TRIAL');

        $subscription = Payments::subscribe(User::factory()->create(), $price->fresh(), Gateway::PayPal);
        $subscription = app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Return);

        Sandbox::capture('paypal-subscription', Sandbox::paypalClient()->get("/v1/billing/subscriptions/{$subscription->gateway_subscription_id}"));

        expect($subscription->checkout_url)->toContain('paypal.com')
            ->and($subscription->status)->toBe(SubscriptionStatus::Incomplete)
            ->and($subscription->gateway_subscription_id)->toStartWith('I-');
    } finally {
        Sandbox::paypalClient()->post("/v1/billing/plans/{$planId}/deactivate");
    }
});

test('a webhook is registered, re-registering keeps it, and a forged delivery fails PayPal\'s verification', function () {
    Sandbox::servePublicly();

    app(RegisterWebhooks::class)->handle(Gateway::PayPal, GatewayMode::Sandbox);
    $webhookId = app(Settings::class)->string(SettingKey::PayPalSandboxWebhookId);

    try {
        app(RegisterWebhooks::class)->handle(Gateway::PayPal, GatewayMode::Sandbox);

        expect(app(Settings::class)->string(SettingKey::PayPalSandboxWebhookId))->toBe($webhookId);

        $forged = Request::create('/webhooks/paypal/sandbox', 'POST', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-360caa42-fca2a594-a5cafa77',
            'HTTP_PAYPAL_TRANSMISSION_ID' => (string) Str::uuid(),
            'HTTP_PAYPAL_TRANSMISSION_SIG' => base64_encode('not a signature'),
            'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601ZuluString(),
        ], content: (string) json_encode(['id' => 'WH-FORGED', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []]));

        expect(fn () => app(PaymentManager::class)->webhookDriver(Gateway::PayPal, GatewayMode::Sandbox)->verifyWebhook($forged))
            ->toThrow(InvalidWebhook::class);
    } finally {
        Sandbox::paypalClient()->delete("/v1/notifications/webhooks/{$webhookId}");
    }
});
