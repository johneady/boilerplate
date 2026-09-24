<?php

namespace App\Payments\Actions;

use App\Models\Payment;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\Money;
use App\Payments\PaymentManager;

/**
 * Take some or all of an authorized payment.
 *
 * The amount is checked against the locked row, the gateway is asked with a
 * key fixed per payment (a payment is captured once, so a double-clicked
 * button sends the same key and the gateway returns the first result), and
 * the outcome is recorded by re-reading the payment through ReconcilePayment.
 * Anything left uncaptured is released by the gateway.
 */
class CapturePayment
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly ReconcilePayment $reconcile,
    ) {}

    /**
     * @throws PaymentNotAllowed
     * @throws GatewayException
     */
    public function handle(Payment $payment, Money $amount): Payment
    {
        $payment->refresh();

        if ($payment->status !== PaymentStatus::Authorized) {
            throw new PaymentNotAllowed(__('Only an authorized payment can be captured.'));
        }

        if ($amount->currency !== $payment->currency || ! $amount->isPositive() || $amount->greaterThan($payment->total())) {
            throw new PaymentNotAllowed(__('Capture between :min and :max.', [
                'min' => Money::of(1, $payment->currency)->format(),
                'max' => $payment->total()->format(),
            ]));
        }

        $this->payments->driverFor($payment)->capture($payment, $amount);

        return $this->reconcile->handle($payment, TransactionSource::Admin);
    }
}
