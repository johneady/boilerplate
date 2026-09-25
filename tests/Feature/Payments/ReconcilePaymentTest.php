<?php

use App\Jobs\RefundDuplicatePayment;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Refund;
use App\Notifications\Payments\DuplicatePaymentRefunded;
use App\Notifications\Payments\PaymentReceipt;
use App\Notifications\Payments\RefundIssued;
use App\Notifications\Payments\RefundReversed;
use App\Payments\Actions\ReconcilePayment;
use App\Payments\Actions\RefundPayment;
use App\Payments\Data\GatewayPaymentState;
use App\Payments\Data\GatewayRefund;
use App\Payments\Data\GatewayStatus;
use App\Payments\Data\GatewayTransaction;
use App\Payments\Drivers\DemoDriver;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Enums\TransactionType;
use App\Payments\Money;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Payments\HeldBooking;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
});

/**
 * What the gateway would report for a payment paid in full, and optionally
 * with refunds on it.
 */
function paidState(Payment $payment, array $refunds = []): GatewayPaymentState
{
    return new GatewayPaymentState(
        status: GatewayStatus::Captured,
        paymentId: 'pi_'.$payment->uuid,
        charges: [new GatewayTransaction('ch_'.$payment->uuid, $payment->total(), CarbonImmutable::now())],
        refunds: $refunds,
    );
}

test('the same gateway state applied again and again records one charge and sends one receipt', function () {
    Notification::fake();
    $link = PaymentLink::factory()->costing(3000)->create();
    $payment = Payments::checkout($link);

    // The return URL, the webhook and its redelivery all report the same capture.
    foreach ([TransactionSource::Return, TransactionSource::Webhook, TransactionSource::Webhook] as $source) {
        app(ReconcilePayment::class)->apply($payment, paidState($payment), $source);
    }

    expect($payment->transactions()->count())->toBe(1)
        ->and($payment->fresh()->amount_captured)->toBe(3000);

    Notification::assertSentOnDemandTimes(PaymentReceipt::class, 1);
});

test('the payable is told about a payment exactly once', function () {
    $booking = HeldBooking::query()->create(['price' => 10000]);
    $payment = Payments::checkout($booking);

    app(ReconcilePayment::class)->apply($payment, paidState($payment), TransactionSource::Return);
    app(ReconcilePayment::class)->apply($payment, paidState($payment), TransactionSource::Webhook);

    expect($booking->fresh()->accepted_count)->toBe(1);
});

test('a late event cannot move a paid payment backwards', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    app(ReconcilePayment::class)->apply($payment, new GatewayPaymentState(GatewayStatus::Open), TransactionSource::Webhook);
    app(ReconcilePayment::class)->apply($payment, new GatewayPaymentState(GatewayStatus::Canceled), TransactionSource::Webhook);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

test('money that settles after a checkout was expired records the payment as paid, tells the payable and sends a receipt', function () {
    Notification::fake();
    $booking = HeldBooking::query()->create(['price' => 10000]);
    $payment = Payments::checkout($booking);
    app(ReconcilePayment::class)->apply($payment, new GatewayPaymentState(GatewayStatus::Expired), TransactionSource::Scheduler);

    app(ReconcilePayment::class)->apply($payment, paidState($payment), TransactionSource::Webhook);

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::Succeeded)
        ->paid_at->not->toBeNull()
        ->and($booking->fresh()->accepted_count)->toBe(1);

    Notification::assertSentOnDemandTimes(PaymentReceipt::class, 1);
});

test('a refund that fails after succeeding is reversed on the ledger and operators are told once', function () {
    Notification::fake();
    Payments::enable(['ops_alert_email' => 'ops@example.test']);
    $payment = Payments::checkout(PaymentLink::factory()->costing(5000)->create());
    app(ReconcilePayment::class)->apply($payment, paidState($payment), TransactionSource::Webhook);
    $refund = app(RefundPayment::class)->handle($payment, Money::of(5000, $payment->currency), 'refund:closed-card');
    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded);

    // The card was closed: the gateway gives the money back to the merchant.
    $failed = new GatewayRefund($refund->gateway_refund_id, $refund->money(), RefundStatus::Failed, CarbonImmutable::now(), $refund->uuid, 'expired_or_canceled_card');
    app(ReconcilePayment::class)->apply($payment, paidState($payment, [$failed]), TransactionSource::Webhook);
    app(ReconcilePayment::class)->apply($payment, paidState($payment, [$failed]), TransactionSource::Webhook);

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::Succeeded)
        ->amount_refunded->toBe(0)
        ->and($refund->fresh())
        ->status->toBe(RefundStatus::Failed)
        ->failure_reason->toBe('expired_or_canceled_card')
        ->and($payment->transactions()->where('type', TransactionType::RefundReversal->value)->sole()->amount)->toBe(5000);

    Notification::assertSentOnDemandTimes(RefundReversed::class, 1);
});

test('a failure while telling the payable rolls the whole reconcile back and sends nothing', function () {
    Notification::fake();
    $booking = HeldBooking::query()->create(['price' => 10000, 'fail_on_accept' => true]);
    $payment = Payments::checkout($booking);

    expect(fn () => app(ReconcilePayment::class)->apply($payment, paidState($payment), TransactionSource::Webhook))
        ->toThrow(RuntimeException::class);

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->paid_at)->toBeNull()
        ->and($payment->transactions()->count())->toBe(0)
        ->and($booking->fresh()->accepted_count)->toBe(0);

    Notification::assertNothingSent();
});

test('a refund made in the gateway dashboard is recorded, ledgered and announced once', function () {
    Notification::fake();
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());

    $dashboardRefund = new GatewayRefund('re_dashboard', Money::fromDecimal('20.00', $payment->currency), RefundStatus::Succeeded, CarbonImmutable::now());

    app(ReconcilePayment::class)->apply($payment, paidState($payment, [$dashboardRefund]), TransactionSource::Webhook);
    app(ReconcilePayment::class)->apply($payment, paidState($payment, [$dashboardRefund]), TransactionSource::Webhook);

    $refund = Refund::sole();

    expect($refund->amount)->toBe(2000)
        ->and($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->initiated_by)->toBeNull()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($payment->fresh()->amount_refunded)->toBe(2000)
        ->and($payment->transactions()->where('type', TransactionType::Refund->value)->sum('amount'))->toEqual(-2000);

    Notification::assertSentOnDemandTimes(RefundIssued::class, 1);
});

test('a first read that finds the charge and a refund together records a paid, partly refunded payment', function () {
    Notification::fake();
    $link = PaymentLink::factory()->singleUse()->costing(5000)->create();
    $payment = Payments::checkout($link);

    // The return and every webhook were lost; by the first read the
    // customer has paid and been partly refunded from the dashboard.
    app(ReconcilePayment::class)->apply($payment, paidState($payment, [
        new GatewayRefund('re_early', Money::of(1000, $payment->currency), RefundStatus::Succeeded, CarbonImmutable::now()),
    ]), TransactionSource::Scheduler);

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::PartiallyRefunded)
        ->paid_at->not->toBeNull()
        ->and($link->fresh()->settled_payment_id)->toBe($payment->id);

    Notification::assertSentOnDemandTimes(PaymentReceipt::class, 1);
});

test('the captured and refunded totals always equal the ledger', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(9000)->create());

    app(ReconcilePayment::class)->apply($payment, paidState($payment, [
        new GatewayRefund('re_1', Money::of(1000, $payment->currency), RefundStatus::Succeeded, CarbonImmutable::now()),
        new GatewayRefund('re_2', Money::of(2500, $payment->currency), RefundStatus::Succeeded, CarbonImmutable::now()),
        new GatewayRefund('re_3', Money::of(500, $payment->currency), RefundStatus::Pending, CarbonImmutable::now()),
    ]), TransactionSource::Webhook);

    $payment->refresh();
    $ledger = $payment->transactions()->get();

    expect($payment->amount_captured)->toBe((int) $ledger->where('type', TransactionType::Charge)->sum('amount'))
        ->and($payment->amount_refunded)->toBe((int) -$ledger->where('type', TransactionType::Refund)->sum('amount'))
        ->and($payment->amount_refunded)->toBe(3500);
});

test('a second payment for a paid single-use link is refunded automatically and operators are told', function () {
    Notification::fake();
    app(Settings::class)->set(SettingKey::OpsAlertEmail, 'ops@example.test');
    $link = PaymentLink::factory()->singleUse()->costing(4000)->create();

    // Two customers opened the same invoice and both got as far as paying.
    $first = Payments::checkout($link);
    $second = Payments::checkout($link);

    app(DemoDriver::class)->simulateCustomer($first, 'approve');
    app(DemoDriver::class)->simulateCustomer($second, 'approve');
    app(ReconcilePayment::class)->handle($first, TransactionSource::Return);
    app(ReconcilePayment::class)->handle($second, TransactionSource::Return);

    expect($link->fresh()->settled_payment_id)->toBe($first->id)
        ->and($first->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($second->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($second->refunds()->sole()->amount)->toBe(4000);

    Notification::assertSentOnDemandTimes(PaymentReceipt::class, 1);
    Notification::assertSentOnDemand(DuplicatePaymentRefunded::class);
});

test('the duplicate refund is made once however many times it is dispatched', function () {
    Queue::fake([RefundDuplicatePayment::class]);
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(4000)->create());

    (new RefundDuplicatePayment($payment->id))->handle(app(RefundPayment::class));
    (new RefundDuplicatePayment($payment->id))->handle(app(RefundPayment::class));

    expect($payment->refunds()->count())->toBe(1)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});
