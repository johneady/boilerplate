<?php

namespace App\Ordering;

use App\Models\Product;

/**
 * One product in the cart and how many of it.
 */
final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
    ) {}

    public function totalCents(): int
    {
        return $this->product->price_cents * $this->quantity;
    }
}
