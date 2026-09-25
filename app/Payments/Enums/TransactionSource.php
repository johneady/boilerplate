<?php

namespace App\Payments\Enums;

/**
 * What told the application about a money movement.
 *
 * Recorded for the trail only; the ledger's uniqueness on the gateway's own
 * transaction id is what makes the same movement seen from two sources (the
 * return URL AND the webhook) record once.
 */
enum TransactionSource: string
{
    case Webhook = 'webhook';

    case Return = 'return';

    case Admin = 'admin';

    case Scheduler = 'scheduler';

    case Demo = 'demo';

    public function label(): string
    {
        return match ($this) {
            self::Webhook => 'Webhook',
            self::Return => 'Customer return',
            self::Admin => 'Administrator',
            self::Scheduler => 'Scheduled check',
            self::Demo => 'Demo gateway',
        };
    }
}
