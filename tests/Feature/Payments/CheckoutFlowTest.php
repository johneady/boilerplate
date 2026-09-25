<?php

use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\TaxRate;
use App\Notifications\Payments\PaymentReceipt;
use App\Payments\Contracts\PaymentDriver;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CheckoutUrls;
use App\Payments\Drivers\DemoDriver;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\TransactionType;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\PaymentManager;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
});

/**
 * Put a driver in front of the Demo gateway whose createCheckout() does
 * something else.
 */
function checkoutsThrough(Closure $createCheckout): void
{
    // The real manager, proxied: StartCheckout reads enabled() and offers()
    // before it reaches the driver, and those must answer for real.
    $manager = Mockery::mock(app(PaymentManager::class))->makePartial();
    $manager->shouldReceive('driverFor')->andReturnUsing(fn (): PaymentDriver => new class($createCheckout) extends DemoDriver
    {
        public function __construct(private Closure $createUsing) {}

        public function createCheckout(Payment $payment, CheckoutUrls $urls): CheckoutSession
        {
            return ($this->createUsing)($payment, $urls, fn () => parent::createCheckout($payment, $urls));
        }
    });

    app()->instance(PaymentManager::class, $manager);
}

test('a checkout records a pending payment priced by the payable, with its tax snapshotted', function () {
    TaxRate::factory()->rate('HST', '13')->create();
    $link = PaymentLink::factory()->costing(10000)->taxable()->create();

    $payment = Payments::checkout($link);

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->subtotal)->toBe(10000)
        ->and($payment->tax_total)->toBe(1300)
        ->and($payment->amount)->toBe(11300)
        ->and($payment->tax_lines)->toEqualCanonicalizing([['name' => 'HST', 'percentage' => '13.000', 'amount' => 1300]])
        ->and($payment->checkout_url)->toBe(URL::signedRoute('payments.demo.show', $payment))
        ->and($payment->payable->is($link))->toBeTrue();
});

test('changing a tax rate later never changes what a payment recorded', function () {
    $rate = TaxRate::factory()->rate('HST', '13')->create();
    $payment = Payments::checkout(PaymentLink::factory()->costing(10000)->taxable()->create());

    $rate->update(['percentage' => '15']);

    expect($payment->fresh()->tax_total)->toBe(1300);
});

test('an untaxed payable is charged no tax even with rates configured', function () {
    TaxRate::factory()->create();

    expect(Payments::checkout(PaymentLink::factory()->costing(10000)->create())->tax_total)->toBe(0);
});

test('the same checkout submitted twice creates one payment', function () {
    $link = PaymentLink::factory()->create();

    $first = Payments::checkout($link, key: 'same-form');
    $second = Payments::checkout($link, key: 'same-form');

    expect($second->id)->toBe($first->id)
        ->and(Payment::count())->toBe(1);
});

test('a checkout is refused when payments are switched off', function () {
    Payments::enable(['payments_enabled' => false]);

    Payments::checkout(PaymentLink::factory()->create());
})->throws(PaymentNotAllowed::class);

test('a checkout is refused through a gateway that is not offered', function () {
    Payments::enable(['stripe_enabled' => false]);

    Payments::checkout(PaymentLink::factory()->create(), Gateway::Stripe);
})->throws(PaymentNotAllowed::class);

test('a checkout is refused on a link that no longer accepts payments', function (Closure $link) {
    Payments::checkout($link());
})->with([
    'inactive' => fn () => PaymentLink::factory()->inactive()->create(),
    'expired' => fn () => PaymentLink::factory()->expired()->create(),
])->throws(PaymentNotAllowed::class);

test('an unreachable gateway leaves the checkout pending, and the sweep settles it', function () {
    checkoutsThrough(fn () => throw new GatewayUnavailable('timed out'));

    $link = PaymentLink::factory()->costing(5000)->create();

    expect(fn () => Payments::checkout($link))->toThrow(GatewayUnavailable::class);

    // Not failed: the session may exist at the gateway, so the outcome is
    // unknown and only the abandoned-checkout sweep may settle the row.
    $payment = Payment::sole();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->checkout_url)->toBeNull();

    app()->forgetInstance(PaymentManager::class);
    $this->travel((int) config('payments.abandoned_after_hours') + 1)->hours();
    $this->artisan('payments:expire-checkouts')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired);
});

test('a definitive gateway refusal marks the payment failed', function () {
    checkoutsThrough(fn () => throw new GatewayException('No such price'));

    $link = PaymentLink::factory()->costing(5000)->create();

    expect(fn () => Payments::checkout($link))->toThrow(GatewayException::class);

    expect(Payment::sole())
        ->status->toBe(PaymentStatus::Failed)
        ->failure_reason->toBe('No such price');
});

test('an approved demo payment is recorded as paid, on the ledger, with one receipt', function () {
    Notification::fake();

    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(2500)->create());

    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->amount_captured)->toBe(2500)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->transactions()->where('type', TransactionType::Charge->value)->sum('amount'))->toEqual(2500);

    Notification::assertSentOnDemandTimes(PaymentReceipt::class, 1);
    Notification::assertSentOnDemand(
        PaymentReceipt::class,
        fn (PaymentReceipt $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'sam@example.test',
    );
});

test('a declined demo payment fails and sends no receipt', function () {
    Notification::fake();

    $payment = Payments::payWithDemo(PaymentLink::factory()->create(), 'decline');

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason)->toBe('The demo card was declined.')
        ->and($payment->transactions()->count())->toBe(0);

    Notification::assertNothingSent();
});

test('a single-use link closes once paid', function () {
    $link = PaymentLink::factory()->singleUse()->create();

    $payment = Payments::payWithDemo($link);

    expect($link->fresh()->settled_payment_id)->toBe($payment->id)
        ->and($link->fresh()->acceptsPayments())->toBeFalse();
});

test('a reusable link keeps accepting payments', function () {
    $link = PaymentLink::factory()->create();

    Payments::payWithDemo($link);
    Payments::payWithDemo($link);

    expect($link->fresh()->acceptsPayments())->toBeTrue()
        ->and($link->payments()->where('status', PaymentStatus::Succeeded->value)->count())->toBe(2);
});
