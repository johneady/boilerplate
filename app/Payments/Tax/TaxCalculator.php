<?php

namespace App\Payments\Tax;

use App\Payments\Money;
use InvalidArgumentException;

/**
 * Work out the taxes on a tax-exclusive subtotal.
 *
 * Each tax is calculated on the subtotal separately and rounded half-up to
 * the cent, which is how stacked taxes (federal + provincial, or state + local
 * sales tax) are shown on a receipt: 5% and 7% on $10.05 are $0.50 and $0.70,
 * not 12% of $10.05 split afterwards. Taxes do not compound: each is charged
 * on the pre-tax price.
 *
 * Percentages arrive as decimal strings ("8.875") and are turned into integer
 * thousandths of a percent before any arithmetic, so no float is involved at
 * any point between the configured rate and the cents charged.
 */
class TaxCalculator
{
    /**
     * The number of thousandths of a percent in a whole (100%).
     */
    private const int SCALE = 100_000;

    /**
     * @param  iterable<array{name: string, percentage: string, registration_number?: string|null}>  $rates
     */
    public function calculate(Money $subtotal, iterable $rates): TaxBreakdown
    {
        if ($subtotal->isNegative()) {
            throw new InvalidArgumentException('Tax is calculated on a positive subtotal.');
        }

        $lines = [];

        foreach ($rates as $rate) {
            $lines[] = new TaxLine(
                name: $rate['name'],
                percentage: $rate['percentage'],
                amount: Money::of(
                    $this->roundHalfUp($subtotal->amount * self::thousandths($rate['percentage'])),
                    $subtotal->currency,
                ),
                registrationNumber: $rate['registration_number'] ?? null,
            );
        }

        return new TaxBreakdown($subtotal, $lines);
    }

    /**
     * A percentage string as integer thousandths of a percent: "9.975" is 9975.
     *
     * At most three decimal places, and between 0 and 100 inclusive; anything
     * else is a configuration error that must not reach a customer's total.
     */
    public static function thousandths(string $percentage): int
    {
        if (preg_match('/^(\d{1,3})(?:\.(\d{1,3}))?$/', trim($percentage), $matches) !== 1) {
            throw new InvalidArgumentException("[{$percentage}] is not a percentage with at most three decimal places.");
        }

        $value = (int) $matches[1] * 1000 + (int) str_pad($matches[2] ?? '', 3, '0');

        if ($value > self::SCALE) {
            throw new InvalidArgumentException("[{$percentage}] is more than 100%.");
        }

        return $value;
    }

    /**
     * Divide a scaled product back to cents, rounding half up.
     */
    private function roundHalfUp(int $scaled): int
    {
        return intdiv($scaled * 2 + self::SCALE, self::SCALE * 2);
    }
}
