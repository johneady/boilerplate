<?php

namespace App\Ordering;

/**
 * What delivery costs for a given order.
 *
 * Pickup is free. Delivery is a flat fee, waived once the subtotal reaches
 * the free-delivery threshold. Both amounts live in config/ordering.php.
 */
class DeliveryFee
{
    public static function for(Fulfilment $fulfilment, int $subtotalCents): int
    {
        if ($fulfilment === Fulfilment::Pickup) {
            return 0;
        }

        if ($subtotalCents >= (int) config('ordering.free_delivery_from_cents')) {
            return 0;
        }

        return (int) config('ordering.delivery_fee_cents');
    }
}
