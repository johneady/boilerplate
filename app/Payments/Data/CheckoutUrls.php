<?php

namespace App\Payments\Data;

/**
 * Where a hosted checkout sends the customer back to.
 */
final readonly class CheckoutUrls
{
    public function __construct(
        public string $returnUrl,
        public string $cancelUrl,
    ) {}
}
