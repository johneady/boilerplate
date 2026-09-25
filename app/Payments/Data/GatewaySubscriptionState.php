<?php

namespace App\Payments\Data;

use App\Payments\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;

/**
 * Everything a gateway currently says about one subscription.
 *
 * Fetched fresh from the gateway whenever anything changes and applied by
 * App\Payments\Actions\ReconcileSubscription, the same way a payment is: an
 * event only says that something changed. A null field is one the gateway
 * did not report, and leaves what is stored alone.
 */
final readonly class GatewaySubscriptionState
{
    /**
     * @param  list<GatewayInvoice>  $invoices  Every paid invoice the gateway reports, oldest first.
     */
    public function __construct(
        public ?SubscriptionStatus $status,
        public ?string $subscriptionId = null,
        public ?string $customerId = null,
        /** The gateway's id for the price being billed (a Stripe Price, a PayPal plan). */
        public ?string $priceId = null,
        public ?CarbonImmutable $currentPeriodStart = null,
        public ?CarbonImmutable $currentPeriodEnd = null,
        public ?CarbonImmutable $trialEndsAt = null,
        /** Null for a gateway that has no such flag (PayPal), where ours is kept. */
        public ?bool $cancelAtPeriodEnd = null,
        public ?CarbonImmutable $canceledAt = null,
        public ?CarbonImmutable $endedAt = null,
        public array $invoices = [],
    ) {}
}
