<?php

namespace App\Payments\Data;

/**
 * The identifiers a webhook event carries that can lead back to a subscription.
 *
 * Tried most specific first by App\Payments\SubscriptionLocator.
 */
final readonly class SubscriptionReference
{
    public function __construct(
        /** Our Subscription's uuid, echoed back through gateway metadata. */
        public ?string $uuid = null,
        public ?string $subscriptionId = null,
        public ?string $checkoutId = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->uuid === null && $this->subscriptionId === null && $this->checkoutId === null;
    }
}
