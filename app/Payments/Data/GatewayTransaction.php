<?php

namespace App\Payments\Data;

use App\Payments\Money;
use Carbon\CarbonImmutable;

/**
 * Money a gateway says it took (a Stripe charge, a PayPal capture).
 */
final readonly class GatewayTransaction
{
    public function __construct(
        public string $id,
        public Money $amount,
        public CarbonImmutable $occurredAt,
    ) {}
}
