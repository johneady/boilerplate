<?php

namespace App\Voltiva;

/**
 * The EU vehicle class a car is approved under.
 *
 * The class is what a buyer's licence has to cover and what caps the car's
 * speed, so the product template derives its registration and licence copy
 * from it rather than asking an editor to write that for every car.
 *
 * Plain English is returned here and translated where it is displayed, like
 * App\Auth\Role -- see .ai/rules/i18n.md.
 */
enum VehicleCategory: string
{
    case L6e = 'l6e';

    case L7e = 'l7e';

    /**
     * The short code, as printed on the approval certificate.
     */
    public function label(): string
    {
        return match ($this) {
            self::L6e => 'L6e',
            self::L7e => 'L7e',
        };
    }

    /**
     * The plain-language name of the class.
     */
    public function description(): string
    {
        return match ($this) {
            self::L6e => 'Light electric quadricycle',
            self::L7e => 'Heavy electric quadricycle',
        };
    }

    /**
     * Who the class is for, in one line.
     */
    public function audience(): string
    {
        return match ($this) {
            self::L6e => 'Town driving from age 15, with an AM moped licence. Limited to 45 km/h.',
            self::L7e => 'Faster, roomier cars for the whole island, with a B1 or car licence. Up to 90 km/h.',
        };
    }

    /**
     * The Spanish licence needed to drive a car of this class.
     */
    public function licence(): string
    {
        return match ($this) {
            self::L6e => 'AM licence (from age 15) or any car licence',
            self::L7e => 'B1 licence (from age 16) or a B car licence',
        };
    }

    /**
     * The legal speed limit of the class, in km/h.
     */
    public function maximumSpeedKmh(): int
    {
        return match ($this) {
            self::L6e => 45,
            self::L7e => 90,
        };
    }

    /**
     * The glossary key explaining this class.
     */
    public function glossaryKey(): string
    {
        return $this->value;
    }
}
