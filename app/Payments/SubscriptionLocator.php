<?php

namespace App\Payments;

use App\Models\Subscription;
use App\Payments\Data\SubscriptionReference;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use Illuminate\Support\Str;

/**
 * Find the subscription a webhook event is about.
 *
 * Like PaymentLocator: most specific first, and always within one gateway
 * and one mode, so a sandbox event can never touch a live subscription.
 */
class SubscriptionLocator
{
    public function find(Gateway $gateway, GatewayMode $mode, SubscriptionReference $reference): ?Subscription
    {
        $query = fn () => Subscription::query()->where('gateway', $gateway->value)->where('mode', $mode->value);

        // Shape-checked first, for the reason PaymentLocator gives.
        if (Str::isUuid($reference->uuid) && ($subscription = $query()->where('uuid', $reference->uuid)->first()) !== null) {
            return $subscription;
        }

        if ($reference->subscriptionId !== null) {
            $subscription = $query()
                ->where(fn ($match) => $match->where('gateway_subscription_id', $reference->subscriptionId)
                    // A PayPal subscription is known by its id from the moment
                    // checkout opens, before the first reconcile records it.
                    ->orWhere('gateway_checkout_id', $reference->subscriptionId))
                ->first();

            if ($subscription !== null) {
                return $subscription;
            }
        }

        if ($reference->checkoutId !== null) {
            return $query()->where('gateway_checkout_id', $reference->checkoutId)->first();
        }

        return null;
    }
}
