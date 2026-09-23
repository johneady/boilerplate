<?php

namespace App\Voltiva;

use Illuminate\Support\Number;

/**
 * Formats the integer cents every price is stored in.
 */
class Money
{
    /**
     * Format an amount of cents in the site currency and the visitor's
     * language -- "€12,990" in English, "12.990 €" in Spanish.
     *
     * Whole euros: car prices are quoted without cents, and a monthly figure
     * rounded to the euro reads as the estimate it is.
     */
    public static function format(int $cents): string
    {
        return (string) Number::currency(
            round($cents / 100),
            (string) config('voltiva.currency'),
            app()->getLocale(),
            0,
        );
    }

    /**
     * The monthly repayment on a loan, in cents, by the standard amortisation
     * formula. Illustrative only -- used by the finance estimate on car pages.
     */
    public static function monthlyPayment(int $principalCents, float $annualRatePercent, int $months): int
    {
        if ($principalCents <= 0 || $months <= 0) {
            return 0;
        }

        $monthlyRate = $annualRatePercent / 100 / 12;

        if ($monthlyRate <= 0.0) {
            return (int) round($principalCents / $months);
        }

        return (int) round($principalCents * $monthlyRate / (1 - (1 + $monthlyRate) ** -$months));
    }
}
