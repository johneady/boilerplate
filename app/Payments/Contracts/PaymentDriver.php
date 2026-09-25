<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Models\Refund;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CheckoutUrls;
use App\Payments\Data\GatewayPaymentState;
use App\Payments\Data\GatewayRefund;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;
use App\Payments\Money;

/**
 * One gateway's side of taking, holding and returning money.
 *
 * A driver is bound to one set of credentials (a gateway in a mode). Every
 * state-changing call carries an idempotency key derived from the payment or
 * refund, so calling it twice -- a retried job, a double-clicked button --
 * does the work once.
 *
 * Drivers only talk to the gateway. They never write payment state: that is
 * App\Payments\Actions\ReconcilePayment's job, applied from fetch().
 *
 * Every method may throw GatewayException (a definitive refusal) or
 * GatewayUnavailable (the outcome is unknown; retry with the same key).
 */
interface PaymentDriver
{
    /**
     * Open a hosted checkout for a pending payment.
     *
     * @throws GatewayException
     * @throws GatewayUnavailable
     */
    public function createCheckout(Payment $payment, CheckoutUrls $urls): CheckoutSession;

    /**
     * Finish a checkout the customer has approved, if the gateway needs a
     * server-side step for that (PayPal's capture or authorize). A no-op for
     * a gateway that completes on its own.
     */
    public function completeCheckout(Payment $payment): void;

    /**
     * Everything the gateway currently says about this payment.
     */
    public function fetch(Payment $payment): GatewayPaymentState;

    /**
     * Take some or all of an authorized amount.
     */
    public function capture(Payment $payment, Money $amount): void;

    /**
     * Release an authorization without taking anything.
     */
    public function void(Payment $payment): void;

    /**
     * Ask the gateway to return money, keyed by the refund's idempotency key.
     */
    public function refund(Payment $payment, Refund $refund): GatewayRefund;

    /**
     * Stop an abandoned checkout from being completed later, where the
     * gateway allows that.
     */
    public function expireCheckout(Payment $payment): void;
}
