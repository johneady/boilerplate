<?php

namespace App\Prints;

use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Support\Number;

/**
 * The price of a set of prints, under the "1 for $x, 3 for $xx" deal.
 *
 * The bundle is applied across the ORDER's total print count rather than per
 * photo: the client's offer is "3 photos for $xx", so a customer who wants two
 * copies of one photo and one of another has bought three photos and gets the
 * deal. Prints beyond a whole bundle are charged at the single price.
 *
 * Prices are read from the settings at quote time, and the resulting amounts
 * are snapshotted onto the order, so changing a price never rewrites what an
 * earlier customer agreed to.
 */
final class PrintPricing
{
    /**
     * How many prints make up one bundle.
     *
     * The client's offer is three, and the deal's shape on screen ("3 for
     * $xx", savings badge, "one more print for the deal" prompt) is written
     * around exactly this number.
     */
    public const int BUNDLE_SIZE = 3;

    /**
     * The configured single-print price, in minor units.
     */
    public static function unitPrice(): int
    {
        return (int) app(Settings::class)->integer(SettingKey::PrintUnitPrice);
    }

    /**
     * The configured bundle price for BUNDLE_SIZE prints, in minor units.
     */
    public static function bundlePrice(): int
    {
        return (int) app(Settings::class)->integer(SettingKey::PrintBundlePrice);
    }

    /**
     * Price a total number of prints.
     */
    public static function quote(int $prints): PrintQuote
    {
        $unitPrice = self::unitPrice();
        $bundlePrice = self::bundlePrice();

        $prints = max(0, $prints);

        $bundles = intdiv($prints, self::BUNDLE_SIZE);
        $singles = $prints % self::BUNDLE_SIZE;

        $listTotal = $prints * $unitPrice;
        $total = ($bundles * $bundlePrice) + ($singles * $unitPrice);

        return new PrintQuote(
            prints: $prints,
            unitPrice: $unitPrice,
            bundlePrice: $bundlePrice,
            bundles: $bundles,
            singles: $singles,
            totalCents: $total,
            listTotalCents: $listTotal,
            savingsCents: max(0, $listTotal - $total),
        );
    }

    /**
     * Format minor units the way the shop talks: "$9.99".
     *
     * Number::currency is documented to return false when the underlying
     * formatter fails; the fallback keeps a till display from ever showing
     * an empty string over a price that is otherwise fine.
     */
    public static function money(int $cents): string
    {
        return Number::currency($cents / 100, 'USD', 'en') ?: '$0.00';
    }
}
