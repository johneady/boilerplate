<?php

namespace App\Ordering;

/**
 * The sections the menu is grouped into, in menu order.
 *
 * A fixed list rather than a table: the café has five sections that change
 * about once a year, and an enum keeps the menu filter from splitting into
 * "Cakes" and "cakes". Labels are plain English, translated where shown.
 */
enum ProductCategory: string
{
    case Breads = 'breads';

    case Pastries = 'pastries';

    case Cakes = 'cakes';

    case Treats = 'treats';

    case Pies = 'pies';

    public function label(): string
    {
        return match ($this) {
            self::Breads => 'Breads',
            self::Pastries => 'Pastries',
            self::Cakes => 'Cakes & Cupcakes',
            self::Treats => 'Cookies & Treats',
            self::Pies => 'Pies & Tarts',
        };
    }

    /**
     * Every case keyed by value, for a select field or filter.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $category) {
            $options[$category->value] = __($category->label());
        }

        return $options;
    }
}
