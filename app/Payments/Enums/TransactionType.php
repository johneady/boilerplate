<?php

namespace App\Payments\Enums;

/**
 * A kind of money movement recorded in the payment_transactions ledger.
 *
 * Only movements of money: an authorization moves nothing (it is a status on
 * the payment), and neither does a void.
 */
enum TransactionType: string
{
    /** Money taken from the customer. Positive. */
    case Charge = 'charge';

    /** Money returned to the customer. Negative. */
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Charge => 'Charge',
            self::Refund => 'Refund',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Charge => 'success',
            self::Refund => 'warning',
        };
    }
}
