<?php

namespace App\Payments\Actions;

use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Payments\Data\CheckoutUrls;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\PaymentManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Start a user's subscription and open the gateway's checkout for it.
 *
 * The same three steps as StartCheckout, never one transaction around a
 * gateway call:
 *
 *   1. record an incomplete subscription, keyed by the request's idempotency
 *      key, taking the user's one live-subscription slot;
 *   2. ask the gateway for a checkout, keyed by the subscription's uuid;
 *   3. record the checkout's id and URL.
 *
 * Step 1 locks the user's row and the slot is a unique column, so of two
 * subscribe attempts racing each other exactly one wins. An earlier checkout
 * the user abandoned does not block a new one: it is re-read (it may have been
 * completed at the gateway since) and, if still unfinished, expired.
 */
class StartSubscription
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly SyncPlan $sync,
        private readonly ReconcileSubscription $reconcile,
    ) {}

    /**
     * @throws PaymentNotAllowed when the user or plan cannot subscribe
     * @throws GatewayException when the gateway refuses or cannot be reached
     */
    public function handle(User $user, PlanPrice $price, Gateway $gateway, string $idempotencyKey): Subscription
    {
        $existing = Subscription::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        $this->ensureAllowed($user, $price, $gateway);
        $this->releaseAbandoned($user);

        $plan = $price->plan ?? throw new PaymentNotAllowed(__('That plan is not available.'));
        $this->sync->ensure($plan, $gateway, $this->payments->mode());

        $subscription = $this->recordIncomplete($user, $price, $gateway, $idempotencyKey);

        if ($subscription->checkout_url !== null || $subscription->status !== SubscriptionStatus::Incomplete) {
            return $subscription;
        }

        $driver = $this->payments->subscriptionDriver($gateway, $subscription->mode);

        try {
            $session = $driver->createSubscriptionCheckout($subscription->load(['user', 'plan', 'price']), new CheckoutUrls(
                returnUrl: $subscription->returnUrl(),
                cancelUrl: $subscription->cancelUrl(),
            ));
        } catch (GatewayUnavailable $e) {
            // The checkout may or may not exist at the gateway, so the
            // outcome is unknown: leave the subscription incomplete and the
            // slot held for the abandoned-checkout sweep, which re-reads one
            // the gateway recorded and expires one it did not -- rather than
            // expire it here and free a slot a live checkout may still claim.
            // The caller reports the exception.
            throw $e;
        } catch (GatewayException $e) {
            // Nothing was started at the gateway, so the slot is freed at
            // once rather than held until the abandoned-checkout sweep.
            $this->reconcile->expire($subscription);

            throw $e;
        }

        $recorded = DB::transaction(function () use ($subscription, $session): Subscription {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            // Recorded whatever happened meanwhile, so the checkout can always
            // be matched to this row if the customer somehow completes it.
            $locked->gateway_checkout_id ??= $session->id;

            if ($locked->status === SubscriptionStatus::Incomplete) {
                $locked->checkout_url = $session->url;
            }

            $locked->save();

            return $locked;
        });

        // Set aside while its checkout was being opened, by a second attempt
        // from another tab: never send the customer to pay for it.
        if ($recorded->status !== SubscriptionStatus::Incomplete) {
            $driver->expireSubscriptionCheckout($recorded);

            throw new PaymentNotAllowed(__('You started another subscription meanwhile. Please try again.'));
        }

        return $recorded;
    }

    private function ensureAllowed(User $user, PlanPrice $price, Gateway $gateway): void
    {
        if (! $this->payments->enabled() || ! in_array($gateway, $this->payments->subscriptionGateways(), true)) {
            throw new PaymentNotAllowed(__('That payment method is not available.'));
        }

        $plan = $price->plan;

        if (! $price->is_active || $plan === null || ! $plan->is_active || $price->currency !== $this->payments->currency()) {
            throw new PaymentNotAllowed(__('That plan is not available.'));
        }

        if (! $user->hasVerifiedEmail()) {
            throw new PaymentNotAllowed(__('Verify your email address before subscribing.'));
        }
    }

    /**
     * Clear the way past an earlier checkout the user never finished.
     */
    private function releaseAbandoned(User $user): void
    {
        $live = Subscription::query()->where('active_user_id', $user->id)->first();

        if ($live === null) {
            return;
        }

        // Re-read before giving up on it: the customer may have finished that
        // checkout after all, in which case they are already subscribed.
        $live = $this->reconcile->expire($live);

        if ($live->status->isLive()) {
            throw new PaymentNotAllowed(__('You already have a subscription. Change or cancel it from your billing page.'));
        }
    }

    private function recordIncomplete(User $user, PlanPrice $price, Gateway $gateway, string $idempotencyKey): Subscription
    {
        try {
            return DB::transaction(function () use ($user, $price, $gateway, $idempotencyKey): Subscription {
                // Serialises concurrent attempts by the same user; the unique
                // slot below is the backstop where the lock is not enough.
                User::query()->lockForUpdate()->findOrFail($user->id);

                if (Subscription::query()->where('active_user_id', $user->id)->exists()) {
                    throw new PaymentNotAllowed(__('You already have a subscription. Change or cancel it from your billing page.'));
                }

                $mode = $this->payments->mode();

                return Subscription::query()->create([
                    'idempotency_key' => $idempotencyKey,
                    'user_id' => $user->id,
                    'active_user_id' => $user->id,
                    'plan_id' => $price->plan_id,
                    'plan_price_id' => $price->id,
                    'gateway' => $gateway,
                    'mode' => $mode,
                    'status' => SubscriptionStatus::Incomplete,
                    'currency' => $price->currency,
                    // Decided under the user's lock, so two attempts at once
                    // cannot both be first.
                    'trial_days' => $user->isEligibleForTrial($mode) ? ($price->plan->trial_days ?? 0) : 0,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return Subscription::query()->where('idempotency_key', $idempotencyKey)->first()
                ?? throw new PaymentNotAllowed(__('You already have a subscription. Change or cancel it from your billing page.'));
        }
    }
}
