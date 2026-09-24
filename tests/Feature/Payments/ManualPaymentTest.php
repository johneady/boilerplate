<?php

use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\TaxRate;
use App\Models\User;
use App\Notifications\Payments\PaymentReceipt;
use App\Payments\Actions\RecordManualPayment;
use App\Payments\Actions\RefundPayment;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\ManualPaymentMethod;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
});

function recordETransfer(PaymentLink $link, int $amount = 5000, string $key = 'manual-key'): Payment
{
    return app(RecordManualPayment::class)->handle(
        payable: $link,
        subtotal: Money::of($amount, Currency::CAD),
        method: ManualPaymentMethod::ETransfer,
        reference: 'CA1x2y3z',
        receivedOn: CarbonImmutable::parse('2026-09-20'),
        customerName: 'Sam Customer',
        customerEmail: 'sam@example.test',
        idempotencyKey: $key,
        recorder: User::factory()->admin()->create(),
    );
}

test('a payment received outside the site is recorded as paid, with tax as at checkout, and receipted', function () {
    Notification::fake();
    TaxRate::factory()->rate('HST', '13')->create();

    $payment = recordETransfer(PaymentLink::factory()->taxable()->create(), 5000);

    expect($payment->gateway)->toBe(Gateway::Manual)
        ->and($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->amount)->toBe(5650)
        ->and($payment->amount_captured)->toBe(5650)
        ->and($payment->manual_method)->toBe(ManualPaymentMethod::ETransfer)
        ->and($payment->manual_received_on->toDateString())->toBe('2026-09-20')
        ->and($payment->transactions()->sole()->occurred_at->toDateString())->toBe('2026-09-20');

    Notification::assertSentOnDemandTimes(PaymentReceipt::class, 1);
});

test('recording the same manual payment twice records it once', function () {
    $link = PaymentLink::factory()->create();

    recordETransfer($link, key: 'same-modal');
    recordETransfer($link, key: 'same-modal');

    expect($link->payments()->count())->toBe(1);
});

test('manual payments cannot be recorded while they are switched off', function () {
    Payments::enable(['manual_payments_enabled' => false]);

    recordETransfer(PaymentLink::factory()->create());
})->throws(PaymentNotAllowed::class);

test('a manual payment cannot be recorded against a link that is already paid', function () {
    $link = PaymentLink::factory()->singleUse()->create();
    Payments::payWithDemo($link);

    recordETransfer($link->fresh());
})->throws(PaymentNotAllowed::class);

test('refunding a manual payment records the money as returned straight away', function () {
    $payment = recordETransfer(PaymentLink::factory()->create(), 5000);

    $refund = app(RefundPayment::class)->handle($payment, Money::of(2000, Currency::CAD), 'manual-refund');

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded);
});
