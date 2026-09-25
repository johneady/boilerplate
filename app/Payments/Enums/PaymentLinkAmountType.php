<?php

namespace App\Payments\Enums;

/**
 * Whether a payment link charges a set price or what the customer enters.
 */
enum PaymentLinkAmountType: string
{
    case Fixed = 'fixed';

    /** Pay-what-you-want between a minimum and maximum: donations, balances. */
    case CustomerEntered = 'customer_entered';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed amount',
            self::CustomerEntered => 'Customer enters the amount',
        };
    }
}
