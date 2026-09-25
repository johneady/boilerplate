<?php

namespace App\Payments\Enums;

use Carbon\CarbonImmutable;

/**
 * How often a plan price bills.
 *
 * Month and year only: both Stripe and PayPal support them, and a plan billed
 * every three months is a month price with an interval_count of 3.
 *
 * Plain English only: translate at the point of display.
 */
enum BillingInterval: string
{
    case Month = 'month';

    case Year = 'year';

    /**
     * "month", "3 months", "year".
     */
    public function describe(int $count = 1): string
    {
        return $count === 1 ? $this->value : "{$count} {$this->value}s";
    }

    /**
     * The unit PayPal's billing plans take.
     */
    public function paypalUnit(): string
    {
        return strtoupper($this->value);
    }

    public function addTo(CarbonImmutable $date, int $count = 1): CarbonImmutable
    {
        return match ($this) {
            self::Month => $date->addMonthsNoOverflow($count),
            self::Year => $date->addYearsNoOverflow($count),
        };
    }

    /**
     * Every case keyed by value, for a select field.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [self::Month->value => 'Monthly', self::Year->value => 'Yearly'];
    }
}
