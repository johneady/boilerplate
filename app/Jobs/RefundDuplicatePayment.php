<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Payments\Actions\RefundPayment;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Exceptions\PaymentNotAllowed;

/**
 * Refund, in full, a payment for something that had already been paid.
 *
 * Dispatched by ReconcilePayment when a single-use payable reports a
 * duplicate. The idempotency key is fixed per payment, so however many times
 * this is dispatched or retried, one refund is made.
 */
class RefundDuplicatePayment extends Job
{
    public function __construct(public int $paymentId) {}

    public function handle(RefundPayment $refunds): void
    {
        $payment = Payment::query()->find($this->paymentId);

        if ($payment === null || ! in_array($payment->status, [PaymentStatus::Succeeded, PaymentStatus::PartiallyRefunded], true)) {
            return;
        }

        try {
            $refunds->handle(
                $payment,
                $payment->refundableMoney(),
                "duplicate:{$payment->uuid}",
                __('Duplicate payment: this had already been paid.'),
            );
        } catch (PaymentNotAllowed) {
            // Nothing left to refund: an administrator got there first.
        }
    }
}
