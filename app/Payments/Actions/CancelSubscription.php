<?php

namespace App\Payments\Actions;

use App\Models\Subscription;
use App\Payments\Data\GatewaySubscriptionState;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\PaymentManager;

/**
 * Cancel a subscription at the end of the paid period, or at once.
 *
 * The gateway is asked first, and the subscription is then re-read from it,
 * so what is recorded is what the gateway did. Where the gateway has no
 * cancel-at-period-end of its own (PayPal, the Demo gateway), the schedule is
 * recorded here and payments:end-subscriptions carries it out.
 *
 * Cancelling at once is for administrators; subscribers cancel at period end,
 * keeping what they have paid for.
 */
class CancelSubscription
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly ReconcileSubscription $reconcile,
    ) {}

    /**
     * @throws PaymentNotAllowed when the subscription is not running
     * @throws GatewayException when the gateway refuses
     */
    public function handle(Subscription $subscription, bool $atPeriodEnd = true): Subscription
    {
        if (! in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
            throw new PaymentNotAllowed(__('This subscription is not running.'));
        }

        if ($atPeriodEnd && $subscription->cancel_at_period_end) {
            return $subscription;
        }

        $driver = $this->payments->subscriptionDriver($subscription->gateway, $subscription->mode);
        $scheduledHere = $atPeriodEnd && $driver->endsCancelledSubscriptionsLocally();

        // Recorded before the gateway is asked: PayPal's suspension looks
        // like a payment failure to a webhook reconciled in between unless
        // the schedule is already on the row.
        if ($scheduledHere) {
            $subscription = $this->reconcile->apply($subscription, new GatewaySubscriptionState(null, cancelAtPeriodEnd: true), TransactionSource::Admin);
        }

        try {
            $driver->cancelSubscription($subscription, $atPeriodEnd);
        } catch (GatewayException $e) {
            if ($scheduledHere) {
                $this->reconcile->apply($subscription, new GatewaySubscriptionState(null, cancelAtPeriodEnd: false), TransactionSource::Admin);
            }

            throw $e;
        }

        return $this->reconcile->handle($subscription, TransactionSource::Admin);
    }
}
