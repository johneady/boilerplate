<?php

namespace App\Payments\Enums;

/**
 * The currencies an installation may charge in.
 *
 * A closed set rather than any ISO 4217 code: each case is one both Stripe
 * (Canada and US accounts) and PayPal Business accept, and one whose minor
 * unit this module has been tested against. Adding a case means checking
 * decimals() -- a zero-decimal currency such as JPY sends whole units to
 * Stripe, not cents.
 *
 * Plain English only, like App\Auth\Role: this is covered by a unit test with
 * no container, where __() is unavailable. Translate at the point of display.
 */
enum Currency: string
{
    case CAD = 'CAD';

    case USD = 'USD';

    /**
     * How many digits the minor unit carries (cents: 2).
     */
    public function decimals(): int
    {
        return match ($this) {
            self::CAD, self::USD => 2,
        };
    }

    /**
     * The label shown wherever a currency is chosen.
     */
    public function label(): string
    {
        return match ($this) {
            self::CAD => 'Canadian dollar (CAD)',
            self::USD => 'US dollar (USD)',
        };
    }

    /**
     * The lowercase code Stripe's API expects.
     */
    public function stripeCode(): string
    {
        return strtolower($this->value);
    }

    /**
     * Every case keyed by value, for a select field.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $currency) {
            $options[$currency->value] = $currency->label();
        }

        return $options;
    }
}
