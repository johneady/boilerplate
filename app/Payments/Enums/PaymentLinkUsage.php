<?php

namespace App\Payments\Enums;

/**
 * Whether a payment link can be paid once or by anyone, any number of times.
 */
enum PaymentLinkUsage: string
{
    /** An invoice: closes once paid. A second concurrent payment is refunded. */
    case SingleUse = 'single_use';

    /** A fixed-price service or a donation page anyone can pay. */
    case Reusable = 'reusable';

    public function label(): string
    {
        return match ($this) {
            self::SingleUse => 'Single use (closes once paid)',
            self::Reusable => 'Reusable',
        };
    }
}
