<?php

namespace App\Shop;

/**
 * Where an order is in its life.
 *
 * Paid on creation: checkout only writes an order once payment has succeeded,
 * so there is no "pending" row for an abandoned basket to leave behind.
 * Fulfilled means the download links have been delivered; refunded returns the
 * licences to stock (see App\Shop\Checkout::refund()).
 */
enum OrderStatus: string
{
    case Paid = 'paid';

    case Fulfilled = 'fulfilled';

    case Refunded = 'refunded';

    /**
     * The label shown wherever a status is displayed to a human.
     */
    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Fulfilled => 'Fulfilled',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * The colour this status is badged with in the admin panel.
     */
    public function color(): string
    {
        return match ($this) {
            self::Paid => 'warning',
            self::Fulfilled => 'success',
            self::Refunded => 'gray',
        };
    }
}
