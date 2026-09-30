<?php

namespace App\Ordering;

use App\Payments\Enums\Currency;
use App\Payments\Money;

/**
 * Formats a price held in cents, in the café's currency.
 */
class Price
{
    /**
     * Formatted for the café's own customers: "$4.25", not "CA$4.25".
     */
    public static function format(int $cents): string
    {
        return Money::of($cents, self::currency())->format((string) config('ordering.locale'));
    }

    public static function currency(): Currency
    {
        return Currency::from((string) config('ordering.currency'));
    }
}
