<?php

namespace App\Payments;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Payments\Data\PaymentReference;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;

/**
 * Find the payment a webhook event is about.
 *
 * Tried most specific first, and always within one gateway AND one mode: a
 * sandbox event must never be able to touch a live payment, even if an id
 * somehow matched.
 */
class PaymentLocator
{
    public function find(Gateway $gateway, GatewayMode $mode, PaymentReference $reference): ?Payment
    {
        $query = fn () => Payment::query()->where('gateway', $gateway->value)->where('mode', $mode->value);

        if ($reference->uuid !== null && ($payment = $query()->where('uuid', $reference->uuid)->first()) !== null) {
            return $payment;
        }

        if ($reference->checkoutId !== null && ($payment = $query()->where('gateway_checkout_id', $reference->checkoutId)->first()) !== null) {
            return $payment;
        }

        if ($reference->paymentId !== null && ($payment = $query()->where('gateway_payment_id', $reference->paymentId)->first()) !== null) {
            return $payment;
        }

        if ($reference->transactionId !== null) {
            $paymentId = PaymentTransaction::query()
                ->where('gateway', $gateway->value)
                ->where('gateway_transaction_id', $reference->transactionId)
                ->value('payment_id');

            return $paymentId === null ? null : $query()->whereKey($paymentId)->first();
        }

        return null;
    }
}
