<?php

namespace App\Shop;

/**
 * The part of the world a package was filmed in, which the catalogue filters on.
 *
 * A fixed list rather than free text so the filter chips cannot fragment into
 * "Europe", "europe" and "EU". Labels are plain English and translated at the
 * point of display, the way App\Auth\Role's are -- see .ai/rules/i18n.md.
 */
enum Region: string
{
    case Europe = 'europe';

    case NorthAmerica = 'north-america';

    case Africa = 'africa';

    case Asia = 'asia';

    case Oceania = 'oceania';

    /**
     * The label shown wherever a region is listed for a human.
     */
    public function label(): string
    {
        return match ($this) {
            self::Europe => 'Europe',
            self::NorthAmerica => 'North America',
            self::Africa => 'Africa',
            self::Asia => 'Asia',
            self::Oceania => 'Oceania',
        };
    }
}
