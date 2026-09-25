<?php

namespace App\Payments\Actions;

use App\Models\Subscription;
use App\Payments\Data\GatewaySubscriptionState;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\PaymentManager;

/**
 * Take back a cancellation scheduled for the end of the period.
 *
 * Only while the period has not yet run out: after that the subscription has
 * ended (or is about to be ended by payments:end-subscriptions) and the
 * customer subscribes again instead.
 */
class ResumeSubscription
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly ReconcileSubscription $reconcile,
    ) {}

    /**
     * @throws PaymentNotAllowed when there is no scheduled cancellation to take back
     * @throws GatewayException when the gateway refuses
     */
    public function handle(Subscription $subscription): Subscription
    {
        if (! $subscription->isCancelScheduled() || ($subscription->ends_at !== null && $subscription->ends_at->isPast())) {
            throw new PaymentNotAllowed(__('This subscription has no cancellation to take back.'));
        }

        $driver = $this->payments->subscriptionDriver($subscription->gateway, $subscription->mode);
        $driver->resumeSubscription($subscription);

        if ($driver->endsCancelledSubscriptionsLocally()) {
            $subscription = $this->reconcile->apply($subscription, new GatewaySubscriptionState(null, cancelAtPeriodEnd: false), TransactionSource::Admin);
        }

        return $this->reconcile->handle($subscription, TransactionSource::Admin);
    }
}
