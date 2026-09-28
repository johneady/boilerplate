<?php

namespace App\Prints\Enums;

/**
 * Whether the lab has been paid for an order.
 *
 * There is deliberately no "unpaid" state. An in-store order is never
 * confronted with a payment request -- that is the client's explicit
 * requirement -- so it is owed at the counter the moment it is placed. A
 * remote order is paid in the flow before the photos are ever queued.
 */
enum PrintPaymentStatus: string
{
    /** In-store: collect cash or card when the customer picks up. */
    case PayAtCounter = 'pay_at_counter';

    /** Remote: taken during checkout, nothing to collect. */
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::PayAtCounter => 'Pay at counter',
            self::Paid => 'Paid',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PayAtCounter => 'amber',
            self::Paid => 'emerald',
        };
    }
}
