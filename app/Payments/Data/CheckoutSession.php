<?php

namespace App\Payments\Data;

/**
 * A hosted checkout the gateway has opened for a payment.
 */
final readonly class CheckoutSession
{
    public function __construct(
        /** The gateway's id for the checkout (a Stripe Checkout Session, a PayPal order). */
        public string $id,
        /** Where to send the customer to pay. */
        public string $url,
    ) {}
}
