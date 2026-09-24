<?php

namespace App\Payments\Actions;

use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Payments\Data\CheckoutUrls;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\PaymentManager;
use Illuminate\Support\Facades\DB;

/**
 * Move a subscription to another plan or price.
 *
 * Stripe makes the change at once and prorates it onto the next invoice.
 * PayPal needs the customer to approve the change at PayPal, and bills the
 * new price from the next cycle with no proration: the change is recorded as
 * pending, the customer is sent to approve it, and ReconcileSubscription
 * settles it once PayPal reports the new plan.
 */
class SwapSubscriptionPlan
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly SyncPlan $sync,
        private readonly ReconcileSubscription $reconcile,
    ) {}

    /**
     * Returns the URL the customer must visit to approve the change, or null
     * when it has been made.
     *
     * @throws PaymentNotAllowed when the change is not possible
     * @throws GatewayException when the gateway refuses
     */
    public function handle(Subscription $subscription, PlanPrice $price): ?string
    {
        $plan = $price->plan;

        if (! in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)) {
            throw new PaymentNotAllowed(__('Only a running subscription can change plan.'));
        }

        if ($subscription->cancel_at_period_end) {
            throw new PaymentNotAllowed(__('Resume the subscription before changing its plan.'));
        }

        if ($price->id === $subscription->plan_price_id || ! $price->is_active || $plan === null || ! $plan->is_active || $price->currency !== $subscription->currency) {
            throw new PaymentNotAllowed(__('That plan is not available.'));
        }

        $this->sync->ensure($plan, $subscription->gateway, $subscription->mode);

        // Re-read: the sync records the gateway's id for the price on the
        // row, not on the instance the caller passed in.
        $price = $price->fresh() ?? $price;

        $approvalUrl = $this->payments->subscriptionDriver($subscription->gateway, $subscription->mode)->swapSubscription(
            $subscription,
            $price,
            new CheckoutUrls($subscription->returnUrl(), $subscription->cancelUrl()),
        );

        if ($approvalUrl !== null) {
            // Our own bookkeeping, not gateway state: which change the
            // customer was sent to approve, shown on the billing page until
            // the gateway reports it made.
            DB::transaction(function () use ($subscription, $price): void {
                $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
                $locked->pending_plan_price_id = $price->id;
                $locked->save();
            });

            return $approvalUrl;
        }

        $this->reconcile->handle($subscription, TransactionSource::Admin);

        return null;
    }
}
