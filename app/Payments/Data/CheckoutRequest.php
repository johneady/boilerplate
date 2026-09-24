<?php

namespace App\Payments\Data;

use App\Payments\Enums\Gateway;
use App\Payments\Money;

/**
 * What a customer submitted to start paying for a payable.
 */
final readonly class CheckoutRequest
{
    public function __construct(
        public Gateway $gateway,
        public string $customerName,
        public string $customerEmail,
        /**
         * Derived from the submitted form, so an identical resubmission finds
         * the payment the first submission created instead of starting a
         * second checkout.
         */
        public string $idempotencyKey,
        /** The amount the customer entered, for payables that let them choose. */
        public ?Money $offeredAmount = null,
        public ?int $userId = null,
    ) {}
}
