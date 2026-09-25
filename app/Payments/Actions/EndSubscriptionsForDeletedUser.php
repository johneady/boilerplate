<?php

namespace App\Payments\Actions;

use App\Models\Subscription;
use App\Models\User;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Exceptions\GatewayException;

/**
 * Stop billing a user whose account is being deleted.
 *
 * Deleting the user nulls user_id and active_user_id on their subscriptions,
 * and with them every way of reaching the customer: no billing page to cancel
 * from, no email for receipts. A subscription still running at the gateway
 * would go on charging them regardless. So a live subscription is cancelled at
 * once -- the account is going, so there is nothing left to keep access to --
 * and an unfinished checkout is expired.
 *
 * Runs before the delete (User's deleting event), and throws if the gateway
 * refuses, which stops the deletion: an account is never removed while the
 * gateway may still be billing it.
 */
class EndSubscriptionsForDeletedUser
{
    public function __construct(
        private readonly CancelSubscription $cancel,
        private readonly ReconcileSubscription $reconcile,
    ) {}

    /**
     * @throws GatewayException when a gateway cannot confirm the cancellation
     */
    public function handle(User $user): void
    {
        $live = Subscription::query()->where('active_user_id', $user->id)->get();

        foreach ($live as $subscription) {
            if ($subscription->status === SubscriptionStatus::Incomplete) {
                // Re-read first: a checkout finished at the last moment has
                // started, and is cancelled below like any running one.
                $subscription = $this->reconcile->expire($subscription);
            }

            if (in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
                $this->cancel->handle($subscription, atPeriodEnd: false);
            }
        }
    }
}
