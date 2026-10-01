<?php

namespace App\Travel;

use Illuminate\Support\Number;

/**
 * Formats tour prices the way the site shows them.
 */
final class Price
{
    /**
     * Format a whole-dollar amount without cents ("$2,450"), which is how tour
     * prices are quoted; an amount with cents keeps them.
     */
    public static function format(int $cents): string
    {
        $currency = (string) config('travel.currency', 'USD');
        $precision = $cents % 100 === 0 ? 0 : 2;

        return (string) Number::currency($cents / 100, $currency, 'en', $precision);
    }
}
