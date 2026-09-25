<?php

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\User;
use App\Notifications\Payments\AbandonedSubscriptionCanceled;
use App\Notifications\Payments\RefundIssued;
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
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Money;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Support\GatewayFakes;
use Tests\Support\PaymentFixtures;
use Tests\Support\Payments;

const PAYPAL_SUBSCRIPTION = 'I-BW452GLLEP1G';
const PAYPAL_SALE = '8HL18326JH8397335';

beforeEach(function () {
    Payments::enable([
        'demo_gateway_enabled' => false,
        'paypal_enabled' => true,
        'paypal_sandbox_client_id' => 'client-id',
        'paypal_sandbox_client_secret' => 'client-secret',
        'paypal_sandbox_webhook_id' => 'WH-ID-123',
    ]);

    Http::preventStrayRequests();
    Notification::fake();
});

/**
 * PayPal, answering from $paypal: the subscription it reports (approval
 * pending until 'approved') and the billing plans it has created.
 *
 * @param  array<string, mixed>  $paypal
 */
function fakePayPalBilling(array &$paypal): void
{
    $paypal += ['approved' => false, 'subscription' => [], 'plans' => 0];
    $base = '/v1/billing/subscriptions/'.PAYPAL_SUBSCRIPTION;

    GatewayFakes::paypal([
        'POST /v1/catalogs/products' => PaymentFixtures::load('paypal/product'),
        'POST /v1/billing/plans' => function () use (&$paypal) {
            $paypal['plans']++;

            return Http::response(PaymentFixtures::load('paypal/billing_plan', ['id' => $paypal['plans'] === 1 ? 'P-5ML4271244454362WXNWU5NQ' : "P-NEW{$paypal['plans']}"]), 201);
        },
        'POST /v1/billing/plans/P-5ML4271244454362WXNWU5NQ/deactivate' => fn () => Http::response(null, 204),
        'POST /v1/billing/subscriptions' => fn () => Http::response(PaymentFixtures::load('paypal/subscription_created'), 201),
        "GET {$base}" => function () use (&$paypal) {
            return Http::response($paypal['approved']
                ? PaymentFixtures::load('paypal/subscription_active', $paypal['subscription'])
                : PaymentFixtures::load('paypal/subscription_created'));
        },
        "GET {$base}/transactions" => function () use (&$paypal) {
            return Http::response($paypal['transactions'] ?? PaymentFixtures::load('paypal/subscription_transactions'));
        },
        "POST {$base}/suspend" => function () use (&$paypal) {
            if ($paypal['suspend_fails'] ?? false) {
                return Http::response(['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'SUBSCRIPTION_CANNOT_BE_SUSPENDED']]], 422);
            }

            $paypal['subscription']['status'] = 'SUSPENDED';

            return Http::response(null, 204);
        },
        "POST {$base}/activate" => function () use (&$paypal) {
            $paypal['subscription']['status'] = 'ACTIVE';

            return Http::response(null, 204);
        },
        "POST {$base}/cancel" => function () use (&$paypal) {
            $paypal['subscription']['status'] = 'CANCELLED';

            return Http::response(null, 204);
        },
        "POST {$base}/revise" => PaymentFixtures::load('paypal/subscription_revised'),
        'GET /v1/payments/sale/'.PAYPAL_SALE => PaymentFixtures::load('paypal/sale'),
        'POST /v1/payments/sale/'.PAYPAL_SALE.'/refund' => fn (Request $request) => Http::response(PaymentFixtures::load('paypal/sale_refund', [
            'id' => 'REFUND-OURS',
            'amount' => ['total' => $request->data()['amount']['total']],
            'invoice_number' => $request->data()['invoice_number'],
        ]), 201),
        'GET /v1/payments/refund/0P209507D6694645N' => PaymentFixtures::load('paypal/sale_refund'),
        'POST /v1/notifications/verify-webhook-signature' => ['verification_status' => 'SUCCESS'],
    ]);
}

function paypalPro(int $trialDays = 0): PlanPrice
{
    TaxRate::factory()->rate('HST', '13')->create();

    return PlanPrice::factory()->for(Plan::factory()->trial($trialDays)->state(['key' => 'pro', 'name' => 'Pro']))->create();
}

/**
 * A user subscribed through PayPal, approved and returned from.
 *
 * @param  array<string, mixed>  $paypal
 */
function paypalSubscriber(array &$paypal): Subscription
{
    fakePayPalBilling($paypal);
    $subscription = Payments::subscribe(User::factory()->create(), paypalPro(), Gateway::PayPal);
    $paypal['approved'] = true;

    return app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Return);
}

test('a plan is synced to PayPal as a product and a billing plan carrying the trial and the tax', function () {
    $paypal = [];
    fakePayPalBilling($paypal);

    $price = paypalPro(trialDays: 14);

    Http::assertSent(function (Request $request) use ($price): bool {
        $body = $request->data();

        return GatewayFakes::is($request, 'POST /v1/billing/plans')
            && $request->hasHeader('PayPal-Request-Id')
            && str_starts_with($request->header('PayPal-Request-Id')[0], "plan-price:{$price->id}.{$price->created_at->getTimestamp()}:sandbox:")
            && $body['product_id'] === 'PROD-5FD60555F23244316'
            && $body['billing_cycles'][0]['tenure_type'] === 'TRIAL'
            && $body['billing_cycles'][0]['frequency'] === ['interval_unit' => 'DAY', 'interval_count' => 14]
            && $body['billing_cycles'][1]['frequency'] === ['interval_unit' => 'MONTH', 'interval_count' => 1]
            && $body['billing_cycles'][1]['pricing_scheme']['fixed_price'] === ['value' => '29.00', 'currency_code' => 'CAD']
            && $body['taxes'] === ['percentage' => '13.000', 'inclusive' => false]
            && $body['payment_preferences']['payment_failure_threshold'] === 3;
    });

    expect($price->fresh()->gatewayRef(Gateway::PayPal, GatewayMode::Sandbox))
        ->id->toBe('P-5ML4271244454362WXNWU5NQ')
        ->tax_name->toBe('HST')
        ->tax_percentage->toBe('13.000');

    app(SyncPlan::class)->handle($price->plan, Gateway::PayPal, GatewayMode::Sandbox);

    // The price's own billing plan, and its no-trial variant; neither again.
    expect($paypal['plans'])->toBe(2);
});

test('a plan with a trial also gets a billing plan without one, for returning customers', function () {
    $paypal = [];
    fakePayPalBilling($paypal);

    $price = paypalPro(trialDays: 14);

    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/billing/plans')
        && count($request->data()['billing_cycles']) === 1
        && $request->data()['billing_cycles'][0]['tenure_type'] === 'REGULAR'
        && $request->data()['taxes'] === ['percentage' => '13.000', 'inclusive' => false]);
    expect($price->fresh()->gatewayRef(Gateway::PayPal, GatewayMode::Sandbox)['no_trial'])
        ->id->toBe('P-NEW2')
        ->and(PlanPrice::findByGatewayRef(Gateway::PayPal, GatewayMode::Sandbox, 'P-NEW2')?->id)->toBe($price->id);
});

test('a customer who has had a trial subscribes on the billing plan without one', function (bool $subscribedBefore, int $trialDays, bool $withoutTrial) {
    $paypal = [];
    fakePayPalBilling($paypal);
    $user = User::factory()->create();

    if ($subscribedBefore) {
        Subscription::factory()->for($user)->status(SubscriptionStatus::Canceled)->create();
    }

    $subscription = Payments::subscribe($user, $price = paypalPro(trialDays: 14), Gateway::PayPal);

    $ref = $price->fresh()->gatewayRef(Gateway::PayPal, GatewayMode::Sandbox);
    $billingPlan = $withoutTrial ? $ref['no_trial']['id'] : $ref['id'];

    expect($subscription->trial_days)->toBe($trialDays)
        ->and($ref['no_trial']['id'])->not->toBe($ref['id']);
    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/billing/subscriptions')
        && $request->data()['plan_id'] === $billingPlan);
})->with([
    'a new customer' => [false, 14, false],
    'a returning customer' => [true, 0, true],
]);

test('a tax change replaces the billing plan, and subscribers on the old one are still recognised', function () {
    $paypal = [];
    $subscription = paypalSubscriber($paypal);

    TaxRate::query()->sole()->update(['percentage' => '15']);

    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/billing/plans/P-5ML4271244454362WXNWU5NQ/deactivate'));
    expect($subscription->price->fresh()->gatewayRef(Gateway::PayPal, GatewayMode::Sandbox))
        ->id->toBe('P-NEW2')
        ->history->toBe(['P-5ML4271244454362WXNWU5NQ'])
        ->and(app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Webhook)->plan_price_id)->toBe($subscription->plan_price_id);
});

test('subscribing sends the customer to approve the subscription at PayPal', function () {
    $paypal = [];
    fakePayPalBilling($paypal);
    $user = User::factory()->create(['name' => 'Sam Q Subscriber', 'email' => 'sam@example.test']);

    $subscription = Payments::subscribe($user, paypalPro(), Gateway::PayPal);

    expect($subscription->checkout_url)->toBe('https://www.sandbox.paypal.com/webapps/billing/subscriptions?ba_token=BA-2M539689T3856352J');
    Http::assertSent(function (Request $request) use ($subscription): bool {
        $body = $request->data();

        return GatewayFakes::is($request, 'POST /v1/billing/subscriptions')
            && $request->hasHeader('PayPal-Request-Id', "{$subscription->uuid}:checkout")
            && $body['plan_id'] === 'P-5ML4271244454362WXNWU5NQ'
            && $body['custom_id'] === $subscription->uuid
            && $body['subscriber'] === ['name' => ['given_name' => 'Sam', 'surname' => 'Q Subscriber'], 'email_address' => 'sam@example.test']
            && $body['application_context']['return_url'] === $subscription->returnUrl();
    });
});

test('an approved subscription is recorded with its first payment and PayPal\'s tax', function () {
    $paypal = [];
    $subscription = paypalSubscriber($paypal);
    $payment = $subscription->payments()->sole();

    expect($subscription)
        ->status->toBe(SubscriptionStatus::Active)
        ->gateway_subscription_id->toBe(PAYPAL_SUBSCRIPTION)
        ->current_period_end->toIso8601ZuluString()->toBe('2026-10-23T10:00:00Z')
        ->and($payment)
        ->gateway_payment_id->toBe(PAYPAL_SALE)
        ->amount_captured->toBe(3277)
        ->tax_total->toBe(377)
        ->subtotal->toBe(2900)
        ->and($payment->tax_lines)->toEqualCanonicalizing([['name' => 'HST', 'percentage' => '13.000', 'amount' => 377]]);
});

test('an activation webhook records a subscriber who never came back from PayPal', function () {
    $paypal = [];
    fakePayPalBilling($paypal);
    $subscription = Payments::subscribe(User::factory()->create(), paypalPro(), Gateway::PayPal);
    $paypal['approved'] = true;

    GatewayFakes::deliverPayPal(PaymentFixtures::load('paypal/event_subscription_activated'))->assertOk();
    GatewayFakes::deliverPayPal(PaymentFixtures::load('paypal/event_subscription_activated'))->assertOk();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->payments()->count())->toBe(1);
});

test('a trial is read from PayPal\'s cycle executions', function () {
    $paypal = [
        'transactions' => ['transactions' => []],
        'subscription' => ['billing_info' => [
            'cycle_executions' => [['tenure_type' => 'TRIAL', 'sequence' => 1, 'cycles_completed' => 0, 'cycles_remaining' => 1, 'total_cycles' => 1]],
            'next_billing_time' => '2026-10-07T10:00:00Z',
        ]],
    ];

    $subscription = paypalSubscriber($paypal);

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->trial_ends_at->toIso8601ZuluString())->toBe('2026-10-07T10:00:00Z')
        ->and($subscription->payments()->count())->toBe(0);
});

test('a failed payment PayPal is retrying makes the subscription past due', function () {
    $paypal = [];
    $subscription = paypalSubscriber($paypal);
    $paypal['subscription']['billing_info']['failed_payments_count'] = 1;

    expect(app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Webhook)->status)->toBe(SubscriptionStatus::PastDue);
});

test('cancelling at period end suspends at PayPal, keeps access, and cancels when the period runs out', function () {
    $paypal = [];
    $subscription = app(CancelSubscription::class)->handle(paypalSubscriber($paypal));

    expect($subscription)
        ->status->toBe(SubscriptionStatus::Active)
        ->cancel_at_period_end->toBeTrue()
        ->ends_at->toIso8601ZuluString()->toBe('2026-10-23T10:00:00Z')
        ->and($subscription->user->subscribed())->toBeTrue();
    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/billing/subscriptions/'.PAYPAL_SUBSCRIPTION.'/suspend'));

    $this->travelTo($subscription->ends_at->addMinute());
    $this->artisan('payments:end-subscriptions')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/billing/subscriptions/'.PAYPAL_SUBSCRIPTION.'/cancel'));
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($subscription->user->subscribed())->toBeFalse();
});

test('resuming re-activates the suspended subscription', function () {
    $paypal = [];
    $subscription = app(ResumeSubscription::class)->handle(app(CancelSubscription::class)->handle(paypalSubscriber($paypal)));

    expect($subscription->cancel_at_period_end)->toBeFalse()
        ->and($subscription->ends_at)->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active);
    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/billing/subscriptions/'.PAYPAL_SUBSCRIPTION.'/activate'));
});

test('a plan change waits for the customer\'s approval at PayPal, then settles', function () {
    $paypal = [];
    $subscription = paypalSubscriber($paypal);
    $yearly = PlanPrice::factory()->for($subscription->plan)->create(['interval' => BillingInterval::Year, 'amount' => 29000]);

    $approvalUrl = app(SwapSubscriptionPlan::class)->handle($subscription, $yearly);

    $newPlanId = $yearly->fresh()->gatewayRef(Gateway::PayPal, GatewayMode::Sandbox)['id'];
    expect($approvalUrl)->toBe('https://www.sandbox.paypal.com/webapps/billing/subscriptions/update?ba_token=BA-8A802366G0648845Y')
        ->and($subscription->fresh()->pending_plan_price_id)->toBe($yearly->id)
        ->and($subscription->fresh()->plan_price_id)->not->toBe($yearly->id);
    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/billing/subscriptions/'.PAYPAL_SUBSCRIPTION.'/revise')
        && $request->data()['plan_id'] === $newPlanId);

    $paypal['subscription']['plan_id'] = $newPlanId;
    $subscription = app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Return);

    expect($subscription->plan_price_id)->toBe($yearly->id)
        ->and($subscription->pending_plan_price_id)->toBeNull();
});

test('a subscription payment is refunded through PayPal\'s sale API', function () {
    $paypal = [];
    $payment = paypalSubscriber($paypal)->payments()->sole();

    $refund = app(RefundPayment::class)->handle($payment, Money::of(1000, Currency::CAD), 'refund:paypal-sub');

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($payment->fresh()->amount_refunded)->toBe(1000);
    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/payments/sale/'.PAYPAL_SALE.'/refund')
        && $request->hasHeader('PayPal-Request-Id', 'refund:paypal-sub')
        && $request->data()['amount'] === ['total' => '10.00', 'currency' => 'CAD']
        && $request->data()['invoice_number'] === $refund->uuid);
});

test('a refund made in the PayPal dashboard is recorded once from its webhook', function () {
    $paypal = [];
    $payment = paypalSubscriber($paypal)->payments()->sole();

    GatewayFakes::deliverPayPal(PaymentFixtures::load('paypal/event_sale_refunded'))->assertOk();
    GatewayFakes::deliverPayPal(PaymentFixtures::load('paypal/event_sale_refunded', ['id' => 'WH-REDELIVERED']))->assertOk();

    $refund = Refund::sole();

    expect($refund)
        ->gateway_refund_id->toBe('0P209507D6694645N')
        ->amount->toBe(1000)
        ->initiated_by->toBeNull()
        ->and($payment->fresh()->amount_refunded)->toBe(1000);
});

test('a scheduled cancellation PayPal refuses is not left recorded', function () {
    $paypal = [];
    $subscription = paypalSubscriber($paypal);
    $paypal['suspend_fails'] = true;

    expect(fn () => app(CancelSubscription::class)->handle($subscription))->toThrow(GatewayException::class)
        ->and($subscription->fresh()->cancel_at_period_end)->toBeFalse();
});

test('a checkout given up on here but approved at PayPal later is cancelled there and refunded', function () {
    $paypal = [];
    fakePayPalBilling($paypal);
    Notification::fake();
    Payments::enable(['ops_alert_email' => 'ops@example.test']);
    $subscription = Payments::subscribe(User::factory()->create(), paypalPro(), Gateway::PayPal);

    $subscription = app(ReconcileSubscription::class)->expire($subscription);
    expect($subscription->status)->toBe(SubscriptionStatus::Expired);

    // The customer approves the page they left open.
    $paypal['approved'] = true;
    GatewayFakes::deliverPayPal(PaymentFixtures::load('paypal/event_subscription_activated'))->assertOk();
    GatewayFakes::deliverPayPal(PaymentFixtures::load('paypal/event_subscription_activated', ['id' => 'WH-AGAIN']))->assertOk();

    Http::assertSent(fn (Request $request): bool => GatewayFakes::is($request, 'POST /v1/billing/subscriptions/'.PAYPAL_SUBSCRIPTION.'/cancel'));
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($subscription->payments()->sole()->fresh()->status)->toBe(PaymentStatus::Refunded);
    Notification::assertSentOnDemandTimes(AbandonedSubscriptionCanceled::class, 1);
    // The customer hears about the refund, not about a renewal.
    Notification::assertSentOnDemandTimes(RefundIssued::class, 1);
    Notification::assertSentOnDemandTimes(SubscriptionRenewed::class, 0);
});
