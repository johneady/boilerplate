<?php

use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\TaxRate;
use App\Payments\Actions\CapturePayment;
use App\Payments\Actions\RefundPayment;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Money;
use App\Payments\PaymentManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Payments\HeldBooking;
use Tests\Support\PaymentFixtures;
use Tests\Support\Payments;

const PAYPAL_ORDER = '5O190127TN364715T';

beforeEach(function () {
    Payments::enable([
        'paypal_enabled' => true,
        'paypal_sandbox_client_id' => 'client-id',
        'paypal_sandbox_client_secret' => 'client-secret',
        'paypal_sandbox_webhook_id' => 'WH-ID-123',
    ]);
});

/**
 * Answer PayPal API calls by method and path, all registered at once (the
 * first matching Http::fake() stub wins). The OAuth token endpoint is always
 * answered.
 *
 * @param  array<string, array<string, mixed>|Closure(Request): mixed>  $routes
 */
function fakePayPal(array $routes): void
{
    $routes = ['POST /v1/oauth2/token' => PaymentFixtures::load('paypal/token'), ...$routes];

    Http::fake(function (Request $request) use ($routes) {
        $key = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);

        if (! array_key_exists($key, $routes)) {
            return Http::response(['name' => 'UNEXPECTED', 'message' => "Unexpected {$key}"], 500);
        }

        $route = $routes[$key];

        return $route instanceof Closure ? $route($request) : Http::response($route);
    });
}

/**
 * The order PayPal reports: approved until it is captured, completed after.
 *
 * @param  array<string, mixed>  $routes
 */
function paypalPayment(array $routes = [], string $captured = 'paypal/order_completed'): Payment
{
    TaxRate::factory()->rate('HST', '13')->create();
    $wasCaptured = false;

    fakePayPal([
        'POST /v2/checkout/orders' => PaymentFixtures::load('paypal/order_created'),
        'GET /v2/checkout/orders/'.PAYPAL_ORDER => function () use (&$wasCaptured, $captured) {
            return Http::response(PaymentFixtures::load($wasCaptured ? $captured : 'paypal/order_approved'));
        },
        'POST /v2/checkout/orders/'.PAYPAL_ORDER.'/capture' => function () use (&$wasCaptured) {
            $wasCaptured = true;

            return Http::response(PaymentFixtures::load('paypal/order_completed'), 201);
        },
        'POST /v2/checkout/orders/'.PAYPAL_ORDER.'/authorize' => function () use (&$wasCaptured) {
            $wasCaptured = true;

            return Http::response(PaymentFixtures::load('paypal/order_authorized'), 201);
        },
        ...$routes,
    ]);

    return Payments::checkout(PaymentLink::factory()->costing(10000)->taxable()->create(['title' => 'Logo design']), Gateway::PayPal);
}

test('a checkout creates a PayPal order carrying the tax breakdown and the payment reference', function () {
    $payment = paypalPayment();

    expect($payment->gateway_checkout_id)->toBe(PAYPAL_ORDER)
        ->and($payment->checkout_url)->toBe('https://www.sandbox.paypal.com/checkoutnow?token='.PAYPAL_ORDER);

    Http::assertSent(function (Request $request) use ($payment): bool {
        if (! str_ends_with($request->url(), '/v2/checkout/orders')) {
            return false;
        }

        $unit = $request->data()['purchase_units'][0];

        return $request->hasHeader('PayPal-Request-Id', $payment->uuid.':checkout')
            && $request->hasHeader('Authorization', 'Bearer A21AAtestToken')
            && $request->data()['intent'] === 'CAPTURE'
            && $unit['custom_id'] === $payment->uuid
            && $unit['amount'] === [
                'currency_code' => 'CAD',
                'value' => '113.00',
                'breakdown' => [
                    'item_total' => ['currency_code' => 'CAD', 'value' => '100.00'],
                    'tax_total' => ['currency_code' => 'CAD', 'value' => '13.00'],
                ],
            ]
            && $request->data()['payment_source']['paypal']['experience_context']['return_url'] === $payment->returnUrl();
    });
});

test('the customer\'s return captures the approved order and records the capture', function () {
    $payment = paypalPayment();

    $this->get($payment->returnUrl())->assertRedirect($payment->receiptUrl());

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->amount_captured)->toBe(11300)
        ->and($payment->transactions()->sole()->gateway_transaction_id)->toBe('3C679366HH908993F');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/capture')
        && $request->hasHeader('PayPal-Request-Id', $payment->uuid.':order-capture')
        && $request->body() === '{}');
});

test('one OAuth token serves every call until it lapses', function () {
    $payment = paypalPayment();

    $this->get($payment->returnUrl());

    Http::assertSentCount(5); // token, create, get, capture, get
    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/oauth2/token')))->toHaveCount(1);
});

test('an expired payment\'s approval is never captured', function () {
    $payment = paypalPayment();
    $payment->forceFill(['status' => PaymentStatus::Expired])->save();

    app(PaymentManager::class)->driverFor($payment)->completeCheckout($payment);

    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/capture'));
});

test('a hold is authorized on return and captured later from its authorization', function () {
    HeldBooking::createTable();
    TaxRate::factory()->rate('HST', '13')->create();
    $state = 'paypal/order_approved';

    // Closures by reference, not arrow functions: fn () captures by value and
    // would never see the order change.
    fakePayPal([
        'POST /v2/checkout/orders' => PaymentFixtures::load('paypal/order_created'),
        'GET /v2/checkout/orders/'.PAYPAL_ORDER => function () use (&$state) {
            return Http::response(PaymentFixtures::load($state));
        },
        'POST /v2/checkout/orders/'.PAYPAL_ORDER.'/authorize' => function () use (&$state) {
            $state = 'paypal/order_authorized';

            return Http::response(PaymentFixtures::load($state), 201);
        },
        'POST /v2/payments/authorizations/0VF52814937998046/capture' => function () use (&$state) {
            $state = 'paypal/order_completed';

            return Http::response(['id' => '3C679366HH908993F', 'status' => 'COMPLETED'], 201);
        },
    ]);

    $payment = Payments::checkout(HeldBooking::query()->create(['price' => 10000]), Gateway::PayPal);
    $this->get($payment->returnUrl());

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::Authorized)
        ->gateway_authorization_id->toBe('0VF52814937998046')
        ->amount_captured->toBe(0);

    $captured = app(CapturePayment::class)->handle($payment->fresh(), Money::of(11300, $payment->currency));

    expect($captured->status)->toBe(PaymentStatus::Succeeded)
        ->and($captured->amount_captured)->toBe(11300);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/authorizations/0VF52814937998046/capture')
        && $request->hasHeader('PayPal-Request-Id', $payment->uuid.':capture')
        && $request->data()['amount'] === ['currency_code' => 'CAD', 'value' => '113.00']);
});

test('a refund is made against the capture, keyed by the refund', function () {
    $payment = paypalPayment([
        'POST /v2/payments/captures/3C679366HH908993F/refund' => fn (Request $request) => Http::response(PaymentFixtures::load('paypal/refund', [
            'custom_id' => $request->data()['custom_id'],
        ]), 201),
    ]);
    $this->get($payment->returnUrl());

    $refund = app(RefundPayment::class)->handle($payment->fresh(), Money::of(2000, $payment->currency), 'refund-modal-2', 'Changed their mind');

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->gateway_refund_id)->toBe('1JU08902781691411')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/refund')
        && $request->hasHeader('PayPal-Request-Id', 'refund-modal-2')
        && $request->data()['amount'] === ['currency_code' => 'CAD', 'value' => '20.00']
        && $request->data()['note_to_payer'] === 'Changed their mind');
});
