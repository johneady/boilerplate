<?php

namespace App\Payments\Actions;

use App\Models\Payment;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\PaymentManager;

/**
 * Release an authorized payment without taking anything.
 */
class VoidPayment
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly ReconcilePayment $reconcile,
    ) {}

    /**
     * @throws PaymentNotAllowed
     * @throws GatewayException
     */
    public function handle(Payment $payment): Payment
    {
        $payment->refresh();

        if ($payment->status !== PaymentStatus::Authorized) {
            throw new PaymentNotAllowed(__('Only an authorized payment can be voided.'));
        }

        $this->payments->driverFor($payment)->void($payment);

        return $this->reconcile->handle($payment, TransactionSource::Admin);
    }
}
