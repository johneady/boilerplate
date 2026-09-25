<?php

use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Payments\Exceptions\ImmutableRecordException;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
});

test('the money-bearing attributes of a payment cannot be changed once written', function (string $attribute, mixed $value) {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    $payment->forceFill([$attribute => $value])->save();
})->with([
    'amount' => ['amount', 1],
    'subtotal' => ['subtotal', 1],
    'currency' => ['currency', 'USD'],
    'customer email' => ['customer_email', 'someone-else@example.test'],
    'gateway' => ['gateway', 'stripe'],
    'mode' => ['mode', 'live'],
    'tax lines' => ['tax_lines', [['name' => 'GST', 'percentage' => '5', 'amount' => 1]]],
])->throws(ImmutableRecordException::class);

test('a gateway id may be filled in once but never replaced', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    $payment->forceFill(['gateway_payment_id' => 'pi_somebody_else'])->save();
})->throws(ImmutableRecordException::class);

test('a payment\'s status moves through the actions without tripping the guard', function () {
    // The guard covers what was charged, not where the payment is in its life.
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());

    expect($payment->paid_at)->not->toBeNull();
});

test('financial records cannot be deleted', function (Closure $record) {
    $record()->delete();
})->with([
    'a payment' => fn () => Payments::payWithDemo(PaymentLink::factory()->create()),
    'a ledger entry' => fn () => Payments::payWithDemo(PaymentLink::factory()->create())->transactions()->first(),
    'a refund' => fn () => Refund::factory()->create(),
])->throws(ImmutableRecordException::class);

test('a ledger entry cannot be edited', function () {
    $entry = Payments::payWithDemo(PaymentLink::factory()->create())->transactions()->first();

    $entry->forceFill(['amount' => 1])->save();
})->throws(ImmutableRecordException::class);

test('not even an administrator may create, edit or delete payment records through the gate', function (string $ability, Closure $target) {
    $admin = User::factory()->admin()->create();

    expect($admin->can($ability, $target()))->toBeFalse();
})->with([
    'create a payment' => ['create', fn () => Payment::class],
    'edit a payment' => ['update', fn () => Payment::factory()->create()],
    'delete a payment' => ['delete', fn () => Payment::factory()->create()],
    'bulk delete payments' => ['delete', fn () => Payment::class],
    'edit a refund' => ['update', fn () => Refund::factory()->create()],
    'delete a ledger entry' => ['delete', fn () => PaymentTransaction::class],
    'delete a webhook event' => ['delete', fn () => WebhookEvent::factory()->create()],
]);

test('an administrator may view payments and move money on them', function (string $ability) {
    $admin = User::factory()->admin()->create();

    expect($admin->can($ability, Payment::factory()->create()))->toBeTrue();
})->with(['view', 'refund', 'capture', 'void']);

test('an ordinary user may do nothing with payments', function (string $ability) {
    expect(User::factory()->create()->can($ability, Payment::factory()->create()))->toBeFalse();
})->with(['view', 'refund', 'capture', 'void']);

test('a payment link that has taken money cannot be deleted, even by an administrator', function () {
    $admin = User::factory()->admin()->create();
    $paid = PaymentLink::factory()->create();
    Payments::payWithDemo($paid);

    expect($admin->can('delete', $paid))->toBeFalse()
        ->and($admin->can('delete', PaymentLink::factory()->create()))->toBeTrue();
});
