<?php

namespace App\Bakery;

/**
 * How a finished order reaches the customer.
 */
enum Fulfilment: string
{
    case Pickup = 'pickup';

    case Delivery = 'delivery';

    /**
     * The label shown wherever the choice is listed for a human.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Pickup',
            self::Delivery => 'Local delivery',
        };
    }
}
