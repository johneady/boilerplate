<?php

namespace App\Payments\Drivers;

use App\Models\Payment;
use App\Models\Refund;
use App\Payments\Contracts\PaymentDriver;
use App\Payments\Data\CheckoutSession;
use App\Payments\Data\CheckoutUrls;
use App\Payments\Data\GatewayPaymentState;
use App\Payments\Data\GatewayRefund;
use App\Payments\Data\GatewayStatus;
use App\Payments\Data\GatewayTransaction;
use App\Payments\Enums\RefundStatus;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\Money;
use Carbon\CarbonImmutable;

/**
 * Money received outside the site, recorded by an administrator.
 *
 * There is no gateway to ask, so the "gateway state" is what was recorded:
 * the money arrived in full on the day given, and a refund is recorded as
 * returned the moment it is entered (the administrator returns the money
 * themselves, by the same means it came in). Going through the same driver
 * contract is what lets a manual payment use the same reconcile, refund,
 * ledger and receipt code as a Stripe one.
 */
class ManualDriver implements PaymentDriver
{
    public function createCheckout(Payment $payment, CheckoutUrls $urls): CheckoutSession
    {
        throw new PaymentNotAllowed('Manual payments are recorded by an administrator, not paid at checkout.');
    }

    public function completeCheckout(Payment $payment): void {}

    public function fetch(Payment $payment): GatewayPaymentState
    {
        $refunds = [];

        foreach ($payment->refunds()->where('status', RefundStatus::Succeeded->value)->get() as $refund) {
            $refunds[] = $this->recorded($refund);
        }

        return new GatewayPaymentState(
            status: GatewayStatus::Captured,
            paymentId: 'manual_'.$payment->uuid,
            charges: [new GatewayTransaction(
                'manual_'.$payment->uuid,
                $payment->total(),
                ($payment->manual_received_on ?? $payment->created_at ?? CarbonImmutable::now())->toImmutable(),
            )],
            refunds: $refunds,
        );
    }

    public function capture(Payment $payment, Money $amount): void
    {
        throw new PaymentNotAllowed('A manual payment has no hold to capture.');
    }

    public function void(Payment $payment): void
    {
        throw new PaymentNotAllowed('A manual payment has no hold to void.');
    }

    public function refund(Payment $payment, Refund $refund): GatewayRefund
    {
        return new GatewayRefund(
            'manual_re_'.$refund->uuid,
            $refund->money(),
            RefundStatus::Succeeded,
            CarbonImmutable::now(),
            $refund->uuid,
        );
    }

    public function expireCheckout(Payment $payment): void {}

    private function recorded(Refund $refund): GatewayRefund
    {
        return new GatewayRefund(
            (string) $refund->gateway_refund_id,
            $refund->money(),
            RefundStatus::Succeeded,
            ($refund->created_at ?? CarbonImmutable::now())->toImmutable(),
            $refund->uuid,
        );
    }
}
