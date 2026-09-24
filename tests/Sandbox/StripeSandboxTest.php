<?php

use App\Jobs\SyncPlans;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\User;
use App\Payments\Actions\CapturePayment;
use App\Payments\Actions\ReconcilePayment;
use App\Payments\Actions\ReconcileSubscription;
use App\Payments\Actions\RefundPayment;
use App\Payments\Actions\RegisterWebhooks;
use App\Payments\Actions\SyncPlan;
use App\Payments\Actions\VoidPayment;
use App\Payments\Data\GatewayStatus;
use App\Payments\Enums\CaptureMethod;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Money;
use App\Payments\PaymentManager;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Payments;
use Tests\Support\Sandbox;

/*
 * The Stripe driver against the real Stripe test mode: every request shape
 * and every response the driver reads, checked against Stripe itself rather
 * than the fixtures the feature suite uses. Run with the Sandbox test suite
 * and STRIPE_SANDBOX_SECRET set to an sk_test_ key.
 */

beforeEach(function () {
    $secret = Sandbox::stripeSecret() ?? $this->markTestSkipped('Set STRIPE_SANDBOX_SECRET to an sk_test_ key to run the Stripe sandbox tests.');

    Http::allowStrayRequests();
    Notification::fake();
    // Plans are synced explicitly below, so each test controls what it creates.
    Queue::fake([SyncPlans::class]);

    Payments::enable([
        'demo_gateway_enabled' => false,
        'stripe_enabled' => true,
        'stripe_sandbox_secret_key' => $secret,
    ]);
});

/**
 * A PaymentIntent confirmed with Stripe's test Visa, as a customer paying on
 * Checkout would leave it, recorded as a pending payment.
 */
function confirmedStripePayment(CaptureMethod $capture = CaptureMethod::Automatic): Payment
{
    $intent = Sandbox::stripe()->paymentIntents->create([
        'amount' => 2000,
        'currency' => 'cad',
        'payment_method' => 'pm_card_visa',
        'confirm' => true,
        'capture_method' => $capture === CaptureMethod::Manual ? 'manual' : 'automatic',
        'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
    ]);

    return Payment::factory()->gateway(Gateway::Stripe)->create([
        'gateway_payment_id' => $intent->id,
        'subtotal' => 2000,
        'amount' => 2000,
        'capture_method' => $capture,
    ]);
}

test('a checkout opens a real Checkout Session, which reads back as open and can be expired', function () {
    $payment = Payments::checkout(PaymentLink::factory()->costing(2500)->create(['title' => 'Sandbox contract']), Gateway::Stripe);
    $driver = app(PaymentManager::class)->driverFor($payment);

    expect($payment->checkout_url)->toStartWith('https://checkout.stripe.com/')
        ->and($driver->fetch($payment)->status)->toBe(GatewayStatus::Open);

    Sandbox::capture('stripe-checkout-session', Sandbox::stripe()->checkout->sessions->retrieve((string) $payment->gateway_checkout_id)->toArray());

    $driver->expireCheckout($payment);

    expect(app(ReconcilePayment::class)->handle($payment, TransactionSource::Scheduler)->status)->toBe(PaymentStatus::Expired);
});

test('a paid PaymentIntent is recorded, then refunded in part and in full', function () {
    $payment = app(ReconcilePayment::class)->handle(confirmedStripePayment(), TransactionSource::Return);

    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->amount_captured)->toBe(2000);

    app(RefundPayment::class)->handle($payment, Money::of(500, Currency::CAD), 'sandbox:'.Str::uuid());
    expect($payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded);

    app(RefundPayment::class)->handle($payment->fresh(), Money::of(1500, Currency::CAD), 'sandbox:'.Str::uuid());
    expect($payment->fresh())
        ->status->toBe(PaymentStatus::Refunded)
        ->amount_refunded->toBe(2000);
});

test('a hold is captured in part, and another is voided', function () {
    $held = app(ReconcilePayment::class)->handle(confirmedStripePayment(CaptureMethod::Manual), TransactionSource::Return);

    expect($held->status)->toBe(PaymentStatus::Authorized)
        ->and($held->authorization_expires_at?->isFuture())->toBeTrue();

    expect(app(CapturePayment::class)->handle($held, Money::of(1500, Currency::CAD)))
        ->status->toBe(PaymentStatus::Succeeded)
        ->amount_captured->toBe(1500);

    $released = app(ReconcilePayment::class)->handle(confirmedStripePayment(CaptureMethod::Manual), TransactionSource::Return);

    expect(app(VoidPayment::class)->handle($released)->status)->toBe(PaymentStatus::Voided);
});

test('a plan syncs to a real product, recurring price and tax rate, and a subscription on it is recorded with Stripe\'s tax', function () {
    TaxRate::factory()->rate('HST', '13')->create();
    $price = PlanPrice::factory()->for(Plan::factory()->state(['name' => 'Sandbox contract '.Str::random(6)]))->create();
    app(SyncPlan::class)->handle($price->plan, Gateway::Stripe, GatewayMode::Sandbox);

    $priceId = $price->fresh()->gatewayRef(Gateway::Stripe, GatewayMode::Sandbox)['id'];
    $remote = Sandbox::stripe()->prices->retrieve($priceId);
    $taxRateId = TaxRate::sole()->gateway_refs['stripe']['sandbox']['id'];

    expect($remote->unit_amount)->toBe(2900)
        ->and($remote->recurring->interval)->toBe('month')
        ->and($remote->tax_behavior)->toBe('exclusive');

    // What Checkout would leave behind: a customer with a card, subscribed.
    $customer = Sandbox::stripe()->customers->create(['email' => 'sandbox-contract@example.test', 'payment_method' => 'pm_card_visa']);
    $card = Sandbox::stripe()->paymentMethods->all(['customer' => $customer->id, 'type' => 'card'])->data[0]->id;
    $stripeSubscription = Sandbox::stripe()->subscriptions->create([
        'customer' => $customer->id,
        'items' => [['price' => $priceId]],
        'default_payment_method' => $card,
        'default_tax_rates' => [$taxRateId],
    ]);

    try {
        $subscription = Subscription::factory()->forPrice($price)->for(User::factory())->status(SubscriptionStatus::Incomplete)->create([
            'gateway' => Gateway::Stripe,
            'gateway_subscription_id' => $stripeSubscription->id,
        ]);

        $subscription = app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Webhook);
        $payment = $subscription->payments()->sole();

        Sandbox::capture('stripe-subscription', Sandbox::stripe()->subscriptions->retrieve($stripeSubscription->id)->toArray());
        Sandbox::capture('stripe-invoices', Sandbox::stripe()->invoices->all(['subscription' => $stripeSubscription->id, 'expand' => ['data.payments']])->toArray());

        expect($subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($subscription->current_period_end?->isFuture())->toBeTrue()
            ->and($payment->status)->toBe(PaymentStatus::Succeeded)
            ->and($payment->subtotal)->toBe(2900)
            ->and($payment->tax_total)->toBe(377)
            ->and($payment->tax_lines[0]['name'] ?? null)->toBe('HST');
    } finally {
        Sandbox::stripe()->subscriptions->cancel($stripeSubscription->id);
        Sandbox::stripe()->prices->update($priceId, ['active' => false]);
        Sandbox::stripe()->products->update((string) $price->plan->fresh()->gatewayRef(Gateway::Stripe, GatewayMode::Sandbox), ['active' => false]);
    }
});

test('a webhook endpoint is registered with its secret, and re-registering leaves one endpoint', function () {
    Sandbox::servePublicly();
    $url = route('payments.webhook', ['gateway' => 'stripe', 'mode' => 'sandbox']);
    $endpointsAtUrl = fn (): array => array_values(array_filter(
        Sandbox::stripe()->webhookEndpoints->all(['limit' => 100])->data,
        fn ($endpoint): bool => $endpoint->url === $url,
    ));

    try {
        app(RegisterWebhooks::class)->handle(Gateway::Stripe, GatewayMode::Sandbox);
        app(RegisterWebhooks::class)->handle(Gateway::Stripe, GatewayMode::Sandbox);

        expect(app(Settings::class)->string(SettingKey::StripeSandboxWebhookSecret))->toStartWith('whsec_')
            ->and($endpointsAtUrl())->toHaveCount(1)
            ->and($endpointsAtUrl()[0]->enabled_events)->toContain('invoice.paid', 'charge.dispute.created');
    } finally {
        foreach ($endpointsAtUrl() as $endpoint) {
            Sandbox::stripe()->webhookEndpoints->delete($endpoint->id);
        }
    }
});
