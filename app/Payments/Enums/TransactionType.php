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

    /**
     * A refund the gateway reported as made, then as failed (Stripe: the card
     * was closed): the money came back to the merchant. Positive, offsetting
     * the refund's own entry, which like every ledger row is never removed.
     */
    case RefundReversal = 'refund_reversal';

    public function label(): string
    {
        return match ($this) {
            self::Charge => 'Charge',
            self::Refund => 'Refund',
            self::RefundReversal => 'Refund reversed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Charge => 'success',
            self::Refund => 'warning',
            self::RefundReversal => 'danger',
        };
    }
}
