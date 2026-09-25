<?php

use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\TaxRate;
use App\Payments\Actions\ReconcilePayment;
use App\Payments\Actions\RefundPayment;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;
use App\Payments\Money;
use App\Payments\PaymentManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\PaymentFixtures;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable([
        'stripe_enabled' => true,
        'stripe_sandbox_secret_key' => 'sk_test_51Example',
        'stripe_sandbox_webhook_secret' => 'whsec_example',
    ]);

    Http::preventStrayRequests();
});

/**
 * Answer Stripe API calls by method and path.
 *
 * @param  array<string, array<string, mixed>|Closure(Request): mixed>  $routes  Keyed "METHOD /v1/path".
 */
function fakeStripe(array $routes): void
{
    Http::fake(function (Request $request) use ($routes) {
        $key = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);

        if (! array_key_exists($key, $routes)) {
            return Http::response(['error' => ['message' => "Unexpected {$key}"]], 500);
        }

        $route = $routes[$key];

        return $route instanceof Closure ? $route($request) : Http::response($route);
    });
}

/**
 * A Stripe payment whose checkout has been opened, with Stripe answering the
 * given routes from then on. Http::fake() stubs accumulate and the first match
 * wins, so every route a test needs is registered here, once.
 *
 * @param  array<string, array<string, mixed>|Closure(Request): mixed>  $routes
 */
function stripePayment(array $routes = []): Payment
{
    TaxRate::factory()->rate('HST', '13')->create();

    fakeStripe(['POST /v1/checkout/sessions' => PaymentFixtures::load('stripe/checkout_session_open'), ...$routes]);

    return Payments::checkout(PaymentLink::factory()->costing(10000)->taxable()->create(['title' => 'Logo design']), Gateway::Stripe);
}

test('a checkout opens a Stripe Checkout Session charging the calculated tax as its own line', function () {
    $payment = stripePayment();

    expect($payment->gateway_checkout_id)->toBe('cs_test_a1B2c3')
        ->and($payment->checkout_url)->toBe('https://checkout.stripe.com/c/pay/cs_test_a1B2c3');

    Http::assertSent(function (Request $request) use ($payment): bool {
        parse_str($request->body(), $body);

        return $request->hasHeader('Authorization', 'Bearer sk_test_51Example')
            && $request->hasHeader('Idempotency-Key', $payment->uuid.':checkout')
            && $body['line_items'][0]['price_data']['unit_amount'] === '10000'
            && $body['line_items'][0]['price_data']['product_data']['name'] === 'Logo design'
            && $body['line_items'][1]['price_data']['unit_amount'] === '1300'
            && $body['line_items'][1]['price_data']['product_data']['name'] === 'HST (13%)'
            && $body['line_items'][0]['price_data']['currency'] === 'cad'
            && $body['payment_intent_data']['capture_method'] === 'automatic'
            && $body['metadata']['payment_uuid'] === $payment->uuid
            && $body['success_url'] === $payment->returnUrl();
    });
});

test('a paid session is recorded with its charge and the payment intent', function () {
    $payment = stripePayment([
        'GET /v1/checkout/sessions/cs_test_a1B2c3' => PaymentFixtures::load('stripe/checkout_session_paid'),
        'GET /v1/refunds' => PaymentFixtures::load('stripe/refund_list'),
    ]);

    $payment = app(ReconcilePayment::class)->handle($payment, TransactionSource::Return);

    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->gateway_payment_id)->toBe('pi_test_P4y')
        ->and($payment->amount_captured)->toBe(11300)
        ->and($payment->transactions()->sole()->gateway_transaction_id)->toBe('ch_test_C5h');
});

test('a held session is recorded as authorized with the card\'s capture deadline', function () {
    $payment = stripePayment(['GET /v1/checkout/sessions/cs_test_a1B2c3' => PaymentFixtures::load('stripe/checkout_session_authorized')]);

    $payment = app(ReconcilePayment::class)->handle($payment, TransactionSource::Webhook);

    expect($payment->status)->toBe(PaymentStatus::Authorized)
        ->and($payment->gateway_authorization_id)->toBe('pi_test_P4y')
        ->and($payment->authorization_expires_at->getTimestamp())->toBe(1790604860);
});

test('a bank debit still settling past the abandonment cutoff is kept pending, not expired', function () {
    $payment = stripePayment([
        'GET /v1/checkout/sessions/cs_test_a1B2c3' => PaymentFixtures::load('stripe/checkout_session_processing'),
        // What Stripe answers for a session that has already been completed.
        'POST /v1/checkout/sessions/cs_test_a1B2c3/expire' => fn () => Http::response(['error' => ['message' => 'This Checkout Session is not open.']], 400),
    ]);

    $this->travel(25)->hours();
    $this->artisan('payments:expire-checkouts')->assertSuccessful();

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::Pending)
        ->expired_at->toBeNull();
});

test('an expired session is recorded as expired', function () {
    $payment = stripePayment(['GET /v1/checkout/sessions/cs_test_a1B2c3' => PaymentFixtures::load('stripe/checkout_session_expired')]);

    expect(app(ReconcilePayment::class)->handle($payment, TransactionSource::Scheduler)->status)->toBe(PaymentStatus::Expired);
});

test('a refund is requested with its own idempotency key and recorded once Stripe confirms it', function () {
    $payment = stripePayment([
        'GET /v1/checkout/sessions/cs_test_a1B2c3' => PaymentFixtures::load('stripe/checkout_session_paid'),
        'GET /v1/refunds' => PaymentFixtures::load('stripe/refund_list'),
        'POST /v1/refunds' => fn (Request $request) => Http::response(PaymentFixtures::load('stripe/refund', [
            'metadata' => ['refund_uuid' => $request->data()['metadata']['refund_uuid']],
        ])),
    ]);

    $payment = app(ReconcilePayment::class)->handle($payment, TransactionSource::Return);

    $refund = app(RefundPayment::class)->handle($payment, Money::of(2000, $payment->currency), 'refund-modal-1');

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->gateway_refund_id)->toBe('re_test_R6f')
        ->and($payment->fresh()->amount_refunded)->toBe(2000);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/v1/refunds')
        && $request->hasHeader('Idempotency-Key', 'refund-modal-1')
        && $request->data()['payment_intent'] === 'pi_test_P4y'
        && $request->data()['amount'] === '2000');
});

test('a Stripe outage is reported as unavailable, to be retried, not as a refusal', function (Closure $response) {
    $payment = stripePayment(['GET /v1/checkout/sessions/cs_test_a1B2c3' => $response]);

    app(PaymentManager::class)->driverFor($payment)->fetch($payment);
})->with([
    'server error' => fn () => Http::response(['error' => ['message' => 'Internal']], 500),
    'rate limited' => fn () => Http::response(['error' => ['message' => 'Too many']], 429),
    'no connection' => fn () => Http::failedConnection(),
])->throws(GatewayUnavailable::class);

test('a request Stripe rejects is reported as a refusal with Stripe\'s reason', function () {
    $payment = stripePayment([
        'GET /v1/checkout/sessions/cs_test_a1B2c3' => fn () => Http::response(['error' => ['type' => 'invalid_request_error', 'message' => 'No such checkout.session']], 404),
    ]);

    expect(fn () => app(PaymentManager::class)->driverFor($payment)->fetch($payment))
        ->toThrow(fn (GatewayException $e) => expect($e)->not->toBeInstanceOf(GatewayUnavailable::class)
            ->and($e->getMessage())->toContain('No such checkout.session'));
});
