<?php

use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Refund;
use App\Models\TaxRate;
use App\Models\User;
use App\Payments\Actions\RefundPayment;
use App\Payments\Contracts\PaymentDriver;
use App\Payments\Data\GatewayRefund;
use App\Payments\Drivers\DemoDriver;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\Money;
use App\Payments\PaymentManager;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
});

function refundOf(Payment $payment, int $amount, string $key = 'refund-key'): Refund
{
    return app(RefundPayment::class)->handle($payment, Money::of($amount, $payment->currency), $key, 'Customer asked', User::factory()->admin()->create());
}

/**
 * Put a driver in front of the Demo gateway whose refund() does something else.
 */
function refundsThrough(Closure $refund): void
{
    $manager = Mockery::mock(PaymentManager::class)->makePartial();
    $manager->shouldReceive('driverFor')->andReturnUsing(fn (): PaymentDriver => new class($refund) extends DemoDriver
    {
        public function __construct(private Closure $refundUsing) {}

        public function refund(Payment $payment, Refund $refund): GatewayRefund
        {
            return ($this->refundUsing)($payment, $refund, fn () => parent::refund($payment, $refund));
        }
    });

    app()->instance(PaymentManager::class, $manager);
}

test('a full refund returns everything and closes the payment', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());

    $refund = refundOf($payment, 5000);

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->initiated_by)->not->toBeNull()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->fresh()->amount_refunded)->toBe(5000);
});

test('several partial refunds add up, and the payment is refunded once they reach the total', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());

    refundOf($payment, 1000, 'a');
    expect($payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded);

    refundOf($payment, 1500, 'b');
    refundOf($payment, 2500, 'c');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->fresh()->amount_refunded)->toBe(5000)
        ->and($payment->refunds()->count())->toBe(3);
});

test('each refund records its share of the tax, and together they return all of it', function () {
    TaxRate::factory()->rate('HST', '13')->create();
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(1000)->taxable()->create());

    refundOf($payment, 333, 'a');
    refundOf($payment, 333, 'b');
    refundOf($payment, 464, 'c');

    expect((int) $payment->refunds()->sum('tax_amount'))->toBe(130);
});

test('more than is still refundable cannot be refunded', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());
    refundOf($payment, 4000, 'first');

    expect(fn () => refundOf($payment, 1001, 'second'))->toThrow(PaymentNotAllowed::class);

    expect($payment->refunds()->count())->toBe(1);
});

test('a refund still waiting on the gateway counts against what can be refunded', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());
    Refund::factory()->for($payment)->create(['amount' => 4000, 'status' => RefundStatus::Pending]);

    expect(fn () => refundOf($payment, 1500))->toThrow(PaymentNotAllowed::class);
});

test('a payment that took no money cannot be refunded', function () {
    $payment = Payments::checkout(PaymentLink::factory()->create());

    refundOf($payment, 100);
})->throws(PaymentNotAllowed::class);

test('the same refund submitted twice is made once', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());
    $calls = 0;
    refundsThrough(function (Payment $payment, Refund $refund, Closure $real) use (&$calls): GatewayRefund {
        $calls++;

        return $real();
    });

    $first = refundOf($payment, 1000, 'double-click');
    $second = refundOf($payment, 1000, 'double-click');

    expect($second->id)->toBe($first->id)
        ->and($calls)->toBe(1)
        ->and($payment->fresh()->amount_refunded)->toBe(1000);
});

test('a gateway refusal marks the refund failed and moves no money', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());
    refundsThrough(fn () => throw new GatewayException('charge_already_refunded'));

    expect(fn () => refundOf($payment, 1000))->toThrow(GatewayException::class);

    expect(Refund::sole())
        ->status->toBe(RefundStatus::Failed)
        ->failure_reason->toBe('charge_already_refunded')
        ->and($payment->fresh()->amount_refunded)->toBe(0)
        ->and($payment->transactions()->count())->toBe(1);
});

test('an unreachable gateway leaves the refund pending, and asking again with its key finishes it', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());
    refundsThrough(fn () => throw new GatewayUnavailable('timed out'));

    $refund = refundOf($payment, 1000);

    expect($refund->status)->toBe(RefundStatus::Pending);

    // The gateway is back; the scheduled reconciliation resubmits it.
    app()->forgetInstance(PaymentManager::class);
    $this->travel(20)->minutes();
    $this->artisan('payments:reconcile-stale')->assertSuccessful();

    expect($refund->fresh()->status)->toBe(RefundStatus::Succeeded)
        ->and($payment->fresh()->amount_refunded)->toBe(1000);
});

test('a refund whose response was lost is recorded once when the gateway is asked again', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());
    // The gateway made the refund, but the response never arrived.
    refundsThrough(function (Payment $payment, Refund $refund, Closure $real): GatewayRefund {
        $real();

        throw new GatewayUnavailable('connection reset');
    });

    $refund = refundOf($payment, 1000);

    app()->forgetInstance(PaymentManager::class);
    $this->travel(20)->minutes();
    $this->artisan('payments:reconcile-stale')->assertSuccessful();

    expect($refund->fresh()->status)->toBe(RefundStatus::Succeeded)
        ->and($payment->refunds()->count())->toBe(1)
        ->and($payment->fresh()->amount_refunded)->toBe(1000);
});
