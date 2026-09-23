<?php

namespace App\Bakery;

/**
 * The sections the menu is grouped into.
 *
 * A fixed list rather than free text so the menu's filter cannot fragment into
 * "Cakes", "cakes" and "Cake". Declaration order is menu order. Labels are plain
 * English and translated at the point of display -- see .ai/rules/i18n.md.
 */
enum MenuCategory: string
{
    case Breads = 'breads';

    case Pastries = 'pastries';

    case Cakes = 'cakes';

    case CookiesAndBars = 'cookies-and-bars';

    case PiesAndTarts = 'pies-and-tarts';

    /**
     * The label shown wherever a category is listed for a human.
     */
    public function label(): string
    {
        return match ($this) {
            self::Breads => 'Breads',
            self::Pastries => 'Pastries',
            self::Cakes => 'Cakes & Cupcakes',
            self::CookiesAndBars => 'Cookies & Bars',
            self::PiesAndTarts => 'Pies & Tarts',
        };
    }

    /**
     * The line shown under the category heading on the menu.
     */
    public function tagline(): string
    {
        return match ($this) {
            self::Breads => 'Slow-fermented and baked the morning you collect.',
            self::Pastries => 'Laminated by hand, best eaten the same day.',
            self::Cakes => 'For birthdays, weddings and every excuse in between.',
            self::CookiesAndBars => 'Boxed and ready for sharing, or not.',
            self::PiesAndTarts => 'All-butter pastry with seasonal fillings.',
        };
    }
}
