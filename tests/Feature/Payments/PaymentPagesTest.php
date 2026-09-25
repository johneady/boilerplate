<?php

use App\Models\Payment;
use App\Models\PaymentLink;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\PaymentManager;
use Illuminate\Support\Facades\URL;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
});

test('the demo checkout approves and returns the customer to their receipt', function () {
    $payment = Payments::checkout(PaymentLink::factory()->costing(4200)->create());

    $this->get(URL::signedRoute('payments.demo.show', $payment))->assertOk()->assertSee('CA$42.00');

    $return = $this->post(URL::signedRoute('payments.demo.store', $payment), ['outcome' => 'approve']);
    $return->assertRedirect($payment->returnUrl());

    $this->get($payment->returnUrl())->assertRedirect($payment->receiptUrl());

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);

    $this->get($payment->receiptUrl())
        ->assertOk()
        ->assertSee(__('Payment received'))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('a customer who cancels is sent back to the link to try again', function () {
    $link = PaymentLink::factory()->create();
    $payment = Payments::checkout($link);

    $this->post(URL::signedRoute('payments.demo.store', $payment), ['outcome' => 'cancel'])
        ->assertRedirect($payment->cancelUrl());

    $this->get($payment->cancelUrl())
        ->assertRedirect($link->url())
        ->assertSessionHas('payment_cancelled', true);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('the demo checkout is closed once the payment has moved on', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    $this->get(URL::signedRoute('payments.demo.show', $payment))->assertNotFound();
    $this->post(URL::signedRoute('payments.demo.store', $payment), ['outcome' => 'approve'])->assertNotFound();
});

test('the demo checkout cannot be opened or answered without its signature', function () {
    $payment = Payments::checkout(PaymentLink::factory()->create());

    $this->get(route('payments.demo.show', $payment))->assertForbidden();
    $this->post(route('payments.demo.store', $payment), ['outcome' => 'approve'])->assertForbidden();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('a receipt cannot be read without its signature', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    $this->get(route('payments.show', $payment))->assertForbidden();
});

test('visiting the return URL never marks a payment paid on its own', function () {
    // Only what the gateway reports counts, not having arrived back.
    $payment = Payments::checkout(PaymentLink::factory()->create());

    $this->get($payment->returnUrl())->assertRedirect();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('switching payments off stops new payments', function () {
    $link = PaymentLink::factory()->create();
    $payment = Payments::checkout($link);

    Payments::enable(['payments_enabled' => false]);

    $this->get($link->url())->assertNotFound();
    $this->get(URL::signedRoute('payments.demo.show', $payment))->assertNotFound();
});

test('switching payments off leaves receipts already sent working', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    Payments::enable(['payments_enabled' => false]);

    $this->get($payment->receiptUrl())->assertOk();
});

test('the return and cancel URLs only work as issued, signed', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    // The uuid is printed on the receipt; knowing it must not lead anyone to
    // a freshly signed receipt.
    $this->get(route('payments.return', $payment))->assertForbidden();
    $this->get(route('payments.cancelled', $payment))->assertForbidden();
    $this->get($payment->returnUrl())->assertRedirect($payment->receiptUrl());
});

test('a return or cancel URL naming something that is not a uuid is not found', function (string $url) {
    // Not a database error: PostgreSQL stores the uuid natively and would
    // reject the lookup if it were ever sent.
    $this->get($url)->assertNotFound();
})->with([
    'payment return' => '/payments/not-a-uuid/return',
    'payment cancelled' => '/payments/not-a-uuid/cancelled',
    'subscription return' => '/subscriptions/not-a-uuid/return',
    'subscription cancelled' => '/subscriptions/not-a-uuid/cancelled',
]);

test('the return URL still works with the parameters a gateway appends', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    $this->get($payment->returnUrl().'&token=5O190127TN364715T&PayerID=QYR5Z8XDVJNXQ')
        ->assertRedirect($payment->receiptUrl());
});

test('the demo gateway cannot settle a payment in production', function () {
    $payment = Payment::factory()->create();

    app()->detectEnvironment(fn () => 'production');

    try {
        expect(fn () => app(PaymentManager::class)->driverFor($payment))->toThrow(PaymentNotAllowed::class)
            ->and(app(PaymentManager::class)->offers(Gateway::Demo))->toBeFalse();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});
