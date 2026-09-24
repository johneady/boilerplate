<?php

namespace App\Payments\Enums;

/**
 * Which set of gateway credentials a payment was made with.
 *
 * Recorded on every gateway-facing row, so switching an installation from
 * sandbox to live never mixes test payments into real ones: reports filter
 * on it, and a webhook for a sandbox payment is still verified with the
 * sandbox secret after the switch.
 */
enum GatewayMode: string
{
    case Sandbox = 'sandbox';

    case Live = 'live';

    public function label(): string
    {
        return match ($this) {
            self::Sandbox => 'Sandbox',
            self::Live => 'Live',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sandbox => 'warning',
            self::Live => 'success',
        };
    }
}
