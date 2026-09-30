<?php

namespace App\Ordering;

/**
 * How the customer gets their order.
 */
enum Fulfilment: string
{
    case Pickup = 'pickup';

    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Pickup',
            self::Delivery => 'Delivery',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pickup => 'shopping-bag',
            self::Delivery => 'truck',
        };
    }
}
