<?php

use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;

test('a payment status only moves forward', function (PaymentStatus $from, PaymentStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    'paid back to pending' => [PaymentStatus::Succeeded, PaymentStatus::Pending],
    'paid back to authorized' => [PaymentStatus::Succeeded, PaymentStatus::Authorized],
    'refunded back to paid' => [PaymentStatus::Refunded, PaymentStatus::Succeeded],
    'partially refunded back to paid' => [PaymentStatus::PartiallyRefunded, PaymentStatus::Succeeded],
    'failed to paid' => [PaymentStatus::Failed, PaymentStatus::Succeeded],
    'voided to captured' => [PaymentStatus::Voided, PaymentStatus::Succeeded],
    'expired to paid' => [PaymentStatus::Expired, PaymentStatus::Succeeded],
]);

test('a payment status may make the forward moves a payment really makes', function (PaymentStatus $from, PaymentStatus $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with([
    [PaymentStatus::Pending, PaymentStatus::Succeeded],
    [PaymentStatus::Pending, PaymentStatus::Authorized],
    [PaymentStatus::Authorized, PaymentStatus::Succeeded],
    [PaymentStatus::Authorized, PaymentStatus::Voided],
    [PaymentStatus::Succeeded, PaymentStatus::PartiallyRefunded],
    [PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded],
    [PaymentStatus::Succeeded, PaymentStatus::Refunded],
    'first read finds a charge and a refund' => [PaymentStatus::Pending, PaymentStatus::PartiallyRefunded],
    'a hold captured and refunded before it was re-read' => [PaymentStatus::Authorized, PaymentStatus::Refunded],
]);

test('reconciling a payment that has not changed is never an illegal move', function (PaymentStatus $status) {
    expect($status->canTransitionTo($status))->toBeTrue();
})->with(PaymentStatus::cases());

test('a refund cannot move back to pending once settled', function (RefundStatus $settled) {
    expect($settled->canTransitionTo(RefundStatus::Pending))->toBeFalse()
        ->and(RefundStatus::Pending->canTransitionTo($settled))->toBeTrue();
})->with([RefundStatus::Succeeded, RefundStatus::Failed]);
