<?php

namespace App\Payments\Data;

use App\Payments\Enums\RefundStatus;
use App\Payments\Money;
use Carbon\CarbonImmutable;

/**
 * A refund as a gateway reports it.
 */
final readonly class GatewayRefund
{
    public function __construct(
        public string $id,
        public Money $amount,
        public RefundStatus $status,
        public CarbonImmutable $occurredAt,
        /** Our Refund's uuid, when the gateway echoes it back; null for a dashboard refund. */
        public ?string $reference = null,
        public ?string $failureReason = null,
    ) {}
}
