<?php

namespace App\Shop;

use Illuminate\Support\Number;

/**
 * Formats the integer cents every price is stored in.
 *
 * One place so the storefront, the checkout and the admin panel cannot disagree
 * about how $1,250.00 is written.
 */
class Money
{
    /**
     * Format an amount in cents in the shop's currency.
     */
    public static function format(int $cents): string
    {
        return (string) Number::currency($cents / 100, in: self::currency());
    }

    /**
     * The ISO 4217 code prices are charged in.
     */
    public static function currency(): string
    {
        return (string) config('shop.currency');
    }
}
