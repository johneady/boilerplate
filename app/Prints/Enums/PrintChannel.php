<?php

namespace App\Prints\Enums;

/**
 * How a print order reached the lab.
 *
 * The distinction the client drew: a customer standing in the retail location
 * is never confronted with a payment request -- they pick up and pay
 * independently -- while a customer elsewhere checks out on their phone and
 * has their prints posted. Everything downstream (the wizard's steps, whether
 * a mailing address is collected, what the console's badge says) branches on
 * this.
 */
enum PrintChannel: string
{
    /** Scanned the QR code at a physical counter. Pays at pickup. */
    case InStore = 'in_store';

    /** Started from the website or a social link. Pays in the flow, posted out. */
    case Remote = 'remote';

    public function label(): string
    {
        return match ($this) {
            self::InStore => 'In store',
            self::Remote => 'Mail out',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStore => 'amber',
            self::Remote => 'sky',
        };
    }
}
