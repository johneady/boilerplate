<?php

use App\Models\BillingCustomer;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\Payments\SubscriptionPaymentFailed;
use App\Notifications\Payments\SubscriptionRenewed;
use App\Payments\Actions\CancelSubscription;
use App\Payments\Actions\ReconcileSubscription;
use App\Payments\Actions\RefundPayment;
use App\Payments\Actions\ResumeSubscription;
use App\Payments\Actions\SwapSubscriptionPlan;
use App\Payments\Actions\SyncPlan;
use App\Payments\Enums\BillingInterval;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Money;
use App\Payments\PaymentManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Support\GatewayFakes;
use Tests\Support\PaymentFixtures;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable([
        'demo_gateway_enabled' => false,
        'stripe_enabled' => true,
        'stripe_sandbox_secret_key' => 'sk_test_51Example',
        'stripe_sandbox_webhook_secret' => 'whsec_example',
    ]);

    Http::preventStrayRequests();
    Notification::fake();
});

/**
 * Stripe Billing, answering from $stripe: the subscription it reports and
 * whether its checkout has completed. Prices are created with an id per
 * interval so a monthly and a yearly price can be told apart.
 *
 * @param  array<string, mixed>  $stripe
 */
function fakeStripeBilling(array &$stripe): void
{
    $stripe += ['completed' => false, 'subscription' => []];

    GatewayFakes::stripe([
        'POST /v1/products' => PaymentFixtures::load('stripe/product'),
        'POST /v1/products/prod_test_Pro' => PaymentFixtures::load('stripe/product'),
        'POST /v1/prices' => fn (Request $request) => Http::response(PaymentFixtures::load('stripe/price', [
            'id' => GatewayFakes::stripeBody($request)['recurring']['interval'] === 'year' ? 'price_test_Yr' : 'price_test_Mo',
        ])),
        'POST /v1/prices/price_test_Mo' => PaymentFixtures::load('stripe/price', ['active' => false]),
        'POST /v1/tax_rates' => PaymentFixtures::load('stripe/tax_rate'),
        'POST /v1/tax_rates/txr_test_HST' => PaymentFixtures::load('stripe/tax_rate', ['active' => false]),
        'POST /v1/customers' => PaymentFixtures::load('stripe/customer'),
        // A new session for each checkout; the first is the one the other
        // routes answer for.
        'POST /v1/checkout/sessions' => function () use (&$stripe) {
            $stripe['sessions'] = ($stripe['sessions'] ?? 0) + 1;

            return Http::response(PaymentFixtures::load('stripe/checkout_session_subscription', ['id' => "cs_test_Sub{$stripe['sessions']}"]));
        },
        'POST /v1/checkout/sessions/cs_test_Sub1/expire' => PaymentFixtures::load('stripe/checkout_session_subscription', ['status' => 'expired']),
        'GET /v1/checkout/sessions/cs_test_Sub1' => function () use (&$stripe) {
            return Http::response(PaymentFixtures::load('stripe/checkout_session_subscription', $stripe['completed']
                ? ['status' => 'complete', 'subscription' => 'sub_test_S1']
                : []));
        },
        'GET /v1/subscriptions/sub_test_S1' => function () use (&$stripe) {
            return Http::response(PaymentFixtures::load('stripe/subscription', $stripe['subscription']));
        },
        'POST /v1/subscriptions/sub_test_S1' => function (Request $request) use (&$stripe) {
            $body = GatewayFakes::stripeBody($request);

            if (isset($body['cancel_at_period_end'])) {
                $stripe['subscription']['cancel_at_period_end'] = $body['cancel_at_period_end'] === 'true';
            }

            if (isset($body['items'][0]['price'])) {
                $stripe['subscription']['items']['data'][0]['price']['id'] = $body['items'][0]['price'];
            }

            return Http::response(PaymentFixtures::load('stripe/subscription', $stripe['subscription']));
        },
        'DELETE /v1/subscriptions/sub_test_S1' => function () use (&$stripe) {
            $stripe['subscription'] = [...$stripe['subscription'], 'status' => 'canceled', 'canceled_at' => time(), 'ended_at' => time()];

            return Http::response(PaymentFixtures::load('stripe/subscription', $stripe['subscription']));
        },
        'GET /v1/invoices' => function () use (&$stripe) {
            return Http::response($stripe['completed'] ? PaymentFixtures::load('stripe/invoice_list_paid') : PaymentFixtures::load('stripe/refund_list'));
        },
        'GET /v1/payment_intents/pi_test_Inv1' => PaymentFixtures::load('stripe/payment_intent_invoice'),
        'GET /v1/refunds' => PaymentFixtures::load('stripe/refund_list'),
        'POST /v1/refunds' => fn (Request $request) => Http::response(PaymentFixtures::load('stripe/refund', [
            'payment_intent' => 'pi_test_Inv1',
            'amount' => (int) GatewayFakes::stripeBody($request)['amount'],
            'metadata' => GatewayFakes::stripeBody($request)['metadata'],
        ])),
        'POST /v1/billing_portal/sessions' => PaymentFixtures::load('stripe/billing_portal_session'),
    ]);
}

function stripePro(int $trialDays = 0): PlanPrice
{
    TaxRate::factory()->rate('HST', '13')->create();

    return PlanPrice::factory()->for(Plan::factory()->trial($trialDays)->state(['key' => 'pro', 'name' => 'Pro']))->create();
}

/**
 * A user subscribed through Stripe, their checkout completed and returned from.
 *
 * @param  array<string, mixed>  $stripe
 */
function stripeSubscriber(array &$stripe, ?User $user = null): Subscription
{
    fakeStripeBilling($stripe);
    $subscription = Payments::subscribe($user ?? User::factory()->create(['email' => 'sam@example.test']), stripePro(), Gateway::Stripe);
    $stripe['completed'] = true;

    return app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Return);
}

test('a plan is synced to Stripe once: its product, its price and the tax rates', function () {
    $stripe = [];
    fakeStripeBilling($stripe);

    $price = stripePro();
    app(SyncPlan::class)->handle($price->plan, Gateway::Stripe, GatewayMode::Sandbox);

    foreach (['POST /v1/products', 'POST /v1/prices', 'POST /v1/tax_rates'] as $create) {
        expect(Http::recorded(fn (Request $request): bool => GatewayFakes::is($request, $create)))->toHaveCount(1, "{$create} was not sent exactly once.");
    }

    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/prices')
        && $request->hasHeader('Idempotency-Key', "plan-price:{$price->id}:sandbox")
        && GatewayFakes::stripeBody($request)['unit_amount'] === '2900'
        && GatewayFakes::stripeBody($request)['recurring'] === ['interval' => 'month', 'interval_count' => '1']
        && GatewayFakes::stripeBody($request)['tax_behavior'] === 'exclusive');
    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/tax_rates')
        && GatewayFakes::stripeBody($request)['percentage'] === '13'
        && GatewayFakes::stripeBody($request)['inclusive'] === 'false');

    expect($price->fresh()->gatewayRef(Gateway::Stripe, GatewayMode::Sandbox)['id'])->toBe('price_test_Mo')
        ->and($price->plan->fresh()->gatewayRef(Gateway::Stripe, GatewayMode::Sandbox))->toBe('prod_test_Pro')
        ->and(app(PaymentManager::class)->subscriptionDriver(Gateway::Stripe, GatewayMode::Sandbox)->isSynced($price->plan->fresh()))->toBeTrue();
});

test('syncing an up-to-date plan creates nothing new at Stripe', function () {
    $stripe = [];
    fakeStripeBilling($stripe);
    $price = stripePro();

    $creates = fn (): int => count(Http::recorded(fn (Request $request): bool => in_array($request->method().' '.parse_url($request->url(), PHP_URL_PATH), ['POST /v1/products', 'POST /v1/prices', 'POST /v1/tax_rates'], true)));
    $before = $creates();

    app(SyncPlan::class)->handle($price->plan, Gateway::Stripe, GatewayMode::Sandbox);

    expect($before)->toBe(3)->and($creates())->toBe(3);
});

test('retiring a price archives it at Stripe', function () {
    $stripe = [];
    fakeStripeBilling($stripe);
    $price = stripePro();

    $price->update(['is_active' => false]);

    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/prices/price_test_Mo')
        && GatewayFakes::stripeBody($request)['active'] === 'false');
});

test('subscribing opens Stripe Checkout in subscription mode for the user\'s Stripe customer', function () {
    $stripe = [];
    fakeStripeBilling($stripe);
    $user = User::factory()->create();

    $subscription = Payments::subscribe($user, stripePro(trialDays: 14), Gateway::Stripe);

    expect($subscription->checkout_url)->toBe('https://checkout.stripe.com/c/pay/cs_test_Sub1')
        ->and($subscription->gateway_checkout_id)->toBe('cs_test_Sub1')
        ->and(BillingCustomer::sole()->gateway_customer_id)->toBe('cus_test_C1');

    Http::assertSent(function (Request $request) use ($subscription): bool {
        $body = GatewayFakes::stripeBody($request);

        return GatewayFakes::is($request, 'POST /v1/checkout/sessions')
            && $request->hasHeader('Idempotency-Key', "{$subscription->uuid}:checkout")
            && $body['mode'] === 'subscription'
            && $body['customer'] === 'cus_test_C1'
            && $body['line_items'][0]['price'] === 'price_test_Mo'
            && $body['subscription_data']['trial_period_days'] === '14'
            && $body['subscription_data']['default_tax_rates'] === ['txr_test_HST']
            && $body['subscription_data']['metadata']['subscription_uuid'] === $subscription->uuid
            && $body['success_url'] === $subscription->returnUrl();
    });
});

test('a returning customer is not created at Stripe twice', function () {
    $stripe = [];
    fakeStripeBilling($stripe);
    $user = User::factory()->create();
    $price = stripePro();

    Payments::subscribe($user, $price, Gateway::Stripe);
    Payments::subscribe($user, $price, Gateway::Stripe);

    expect(Http::recorded(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/customers')))->toHaveCount(1);
});

test('the return records the subscription and its first invoice, with the tax Stripe charged', function () {
    $stripe = [];
    $subscription = stripeSubscriber($stripe);
    $payment = $subscription->payments()->sole();

    expect($subscription)
        ->status->toBe(SubscriptionStatus::Active)
        ->gateway_subscription_id->toBe('sub_test_S1')
        ->gateway_customer_id->toBe('cus_test_C1')
        ->current_period_end->getTimestamp()->toBe(1792592000)
        ->and($payment)
        ->gateway_payment_id->toBe('pi_test_Inv1')
        ->subtotal->toBe(2900)
        ->tax_total->toBe(377)
        ->amount_captured->toBe(3277)
        ->and($payment->tax_lines)->toEqualCanonicalizing([['name' => 'HST', 'percentage' => '13.000', 'amount' => 377]])
        ->and($payment->transactions()->sole()->gateway_transaction_id)->toBe('ch_test_Inv1');
});

test('invoice and subscription webhooks, replayed and out of order, record each payment once', function () {
    $stripe = [];
    $subscription = stripeSubscriber($stripe);

    GatewayFakes::deliverStripe(PaymentFixtures::load('stripe/event_invoice_paid'))->assertOk();
    GatewayFakes::deliverStripe(PaymentFixtures::load('stripe/event_subscription_updated'))->assertOk();
    GatewayFakes::deliverStripe(PaymentFixtures::load('stripe/event_invoice_paid'))->assertOk();

    expect($subscription->payments()->count())->toBe(1)
        ->and(WebhookEvent::query()->pluck('status')->map->value->all())->toBe(['processed', 'processed']);

    Notification::assertSentOnDemandTimes(SubscriptionRenewed::class, 1);
});

test('a subscription checkout completed webhook for an unknown subscription is ignored, not matched to a payment', function () {
    GatewayFakes::deliverStripe(PaymentFixtures::load('stripe/event_checkout_session_completed', ['data' => ['object' => ['id' => 'cs_test_Other', 'mode' => 'subscription']]]))->assertOk();

    expect(WebhookEvent::sole()->status->value)->toBe('ignored');
});

test('cancelling at period end and resuming set Stripe\'s own flag', function () {
    $stripe = [];
    $subscription = stripeSubscriber($stripe);

    $subscription = app(CancelSubscription::class)->handle($subscription);
    expect($subscription->cancel_at_period_end)->toBeTrue()
        ->and($subscription->ends_at->getTimestamp())->toBe(1792592000);

    $subscription = app(ResumeSubscription::class)->handle($subscription);
    expect($subscription->cancel_at_period_end)->toBeFalse();

    $flags = collect(Http::recorded(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/subscriptions/sub_test_S1')))
        ->map(fn (array $pair): string => GatewayFakes::stripeBody($pair[0])['cancel_at_period_end'])
        ->values()
        ->all();

    expect($flags)->toBe(['true', 'false']);
});

test('Stripe ends its own cancelled subscriptions; the sweep leaves them to it', function () {
    $stripe = [];
    $subscription = app(CancelSubscription::class)->handle(stripeSubscriber($stripe));

    $this->travelTo($subscription->ends_at->addHour());
    $this->artisan('payments:end-subscriptions')->assertSuccessful();

    Http::assertNotSent(fn (Request $request): bool => GatewayFakes::is($request, 'DELETE /v1/subscriptions/sub_test_S1'));
});

test('cancelling now cancels at Stripe and ends access', function () {
    $stripe = [];
    $subscription = app(CancelSubscription::class)->handle(stripeSubscriber($stripe), atPeriodEnd: false);

    expect($subscription->status)->toBe(SubscriptionStatus::Canceled)
        ->and($subscription->user->subscribed())->toBeFalse();
});

test('changing plan swaps the subscription item with prorations', function () {
    $stripe = [];
    $subscription = stripeSubscriber($stripe);
    $yearly = PlanPrice::factory()->for($subscription->plan)->create(['interval' => BillingInterval::Year, 'amount' => 29000]);

    expect(app(SwapSubscriptionPlan::class)->handle($subscription, $yearly))->toBeNull();

    Http::assertSent(function (Request $request): bool {
        $body = GatewayFakes::stripeBody($request);

        return GatewayFakes::is($request, 'POST /v1/subscriptions/sub_test_S1')
            && ($body['items'][0] ?? null) === ['id' => 'si_test_1', 'price' => 'price_test_Yr']
            && $body['proration_behavior'] === 'create_prorations';
    });

    expect($subscription->fresh()->plan_price_id)->toBe($yearly->id);
});

test('a failed renewal reported by Stripe opens the grace period', function () {
    $stripe = [];
    $subscription = stripeSubscriber($stripe);
    $stripe['subscription']['status'] = 'past_due';

    $subscription = app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Webhook);

    expect($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->user->subscribed())->toBeTrue();

    Notification::assertSentTo($subscription->user, SubscriptionPaymentFailed::class);
});

test('a subscription payment is refunded against its PaymentIntent', function () {
    $stripe = [];
    $payment = stripeSubscriber($stripe)->payments()->sole();

    app(RefundPayment::class)->handle($payment, Money::of(1000, Currency::CAD), 'refund:stripe-sub');

    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/refunds')
        && GatewayFakes::stripeBody($request)['payment_intent'] === 'pi_test_Inv1'
        && GatewayFakes::stripeBody($request)['amount'] === '1000');
    expect($payment->fresh()->amount_refunded)->toBe(1000);
});

test('the payment method is changed in Stripe\'s billing portal', function () {
    $stripe = [];
    $subscription = stripeSubscriber($stripe);

    $url = app(PaymentManager::class)->subscriptionDriver(Gateway::Stripe, GatewayMode::Sandbox)->paymentMethodUrl($subscription, route('billing.edit'));

    expect($url)->toBe('https://billing.stripe.com/p/session/test_YWNjdF8x');
    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/billing_portal/sessions')
        && GatewayFakes::stripeBody($request) === ['customer' => 'cus_test_C1', 'return_url' => route('billing.edit')]);
});

test('switching back and forth between prices sends each switch, not a replay of the first', function () {
    $stripe = [];
    $subscription = stripeSubscriber($stripe);
    $monthly = $subscription->price;
    $yearly = PlanPrice::factory()->for($subscription->plan)->create(['interval' => BillingInterval::Year, 'amount' => 29000]);

    foreach ([$yearly, $monthly, $yearly] as $price) {
        $this->travel(1)->minute();
        app(SwapSubscriptionPlan::class)->handle($subscription->fresh(), $price);
    }

    $keys = collect(Http::recorded(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/subscriptions/sub_test_S1')))
        ->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0]);

    expect($keys)->toHaveCount(3)
        ->and($keys->unique())->toHaveCount(3)
        ->and($subscription->fresh()->plan_price_id)->toBe($yearly->id);
});

test('a tax rate changed and changed back gets a new Stripe rate each time', function () {
    $stripe = [];
    fakeStripeBilling($stripe);
    $price = stripePro();
    $rate = TaxRate::query()->sole();

    foreach (['15', '13'] as $percentage) {
        $rate->update(['percentage' => $percentage]);
        app(SyncPlan::class)->handle($price->plan, Gateway::Stripe, GatewayMode::Sandbox);
    }

    $keys = collect(Http::recorded(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/tax_rates')))
        ->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0]);

    expect($keys)->toHaveCount(3)->and($keys->unique())->toHaveCount(3);
});
