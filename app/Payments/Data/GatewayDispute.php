<?php

namespace App\Payments\Data;

use App\Payments\Enums\DisputeStatus;
use App\Payments\Money;
use Carbon\CarbonImmutable;

/**
 * A dispute as the gateway currently reports it.
 */
final readonly class GatewayDispute
{
    public function __construct(
        public string $id,
        public DisputeStatus $status,
        /** The payment disputed, as the gateway names it. */
        public PaymentReference $payment,
        /** Null when the amount is in a currency this module does not handle. */
        public ?Money $amount = null,
        public ?string $reason = null,
        public ?CarbonImmutable $evidenceDueBy = null,
    ) {}
}
