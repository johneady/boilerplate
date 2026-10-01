<?php

namespace App\Travel;

/**
 * The kind of trip a tour is, which is how most travellers start browsing.
 */
enum TourStyle: string
{
    case Adventure = 'adventure';

    case Culture = 'culture';

    case Beach = 'beach';

    case Wildlife = 'wildlife';

    case FoodAndWine = 'food-and-wine';

    /**
     * Plain English, translated at the point of display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Adventure => 'Adventure',
            self::Culture => 'Culture & history',
            self::Beach => 'Beach & islands',
            self::Wildlife => 'Wildlife & nature',
            self::FoodAndWine => 'Food & wine',
        };
    }

    /**
     * A heroicon name for chips and cards.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Adventure => 'bolt',
            self::Culture => 'building-library',
            self::Beach => 'sun',
            self::Wildlife => 'eye',
            self::FoodAndWine => 'cake',
        };
    }

    /**
     * Every case keyed by value, for a select field.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $style) {
            $options[$style->value] = __($style->label());
        }

        return $options;
    }
}
