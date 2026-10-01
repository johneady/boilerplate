<?php

namespace App\Travel;

/**
 * The parts of the world the destinations are grouped under.
 */
enum Region: string
{
    case Europe = 'europe';

    case Africa = 'africa';

    case AsiaPacific = 'asia-pacific';

    case Americas = 'americas';

    /**
     * Plain English, translated at the point of display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Europe => 'Europe',
            self::Africa => 'Africa',
            self::AsiaPacific => 'Asia & Pacific',
            self::Americas => 'The Americas',
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

        foreach (self::cases() as $region) {
            $options[$region->value] = __($region->label());
        }

        return $options;
    }
}
