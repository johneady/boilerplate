<?php

namespace App\Payments\Data;

/**
 * The identifiers a webhook event carries that can lead back to a payment.
 *
 * Whichever is present is tried, most specific first, by
 * App\Payments\PaymentLocator.
 */
final readonly class PaymentReference
{
    public function __construct(
        /** Our Payment's uuid, echoed back through gateway metadata. */
        public ?string $uuid = null,
        public ?string $checkoutId = null,
        public ?string $paymentId = null,
        /** A gateway transaction id already in the ledger (a PayPal capture). */
        public ?string $transactionId = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->uuid === null && $this->checkoutId === null && $this->paymentId === null && $this->transactionId === null;
    }
}
