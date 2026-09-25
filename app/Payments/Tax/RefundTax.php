<?php

namespace App\Payments\Tax;

/**
 * The share of a refund that is tax.
 *
 * A refund returns tax in proportion to what it returns, so receipts and tax
 * remittances stay right. Rounding each partial refund separately would let
 * the tax refunded drift a cent away from the tax charged, so the refund that
 * brings the payment to fully refunded takes exactly what is left.
 *
 * Pure integer arithmetic on minor units, so it is unit-testable and cannot
 * disagree with itself between the refund modal and the receipt.
 */
final class RefundTax
{
    /**
     * @param  int  $amount  The payment total (subtotal + tax) as charged.
     * @param  int  $taxTotal  The tax within that total.
     * @param  int  $captured  What was actually taken (less than $amount after a partial capture).
     * @param  int  $alreadyRefunded  Refunds already made or pending, excluding this one.
     * @param  int  $alreadyRefundedTax  The tax share of those refunds.
     * @param  int  $refund  The amount being refunded now.
     */
    public static function share(int $amount, int $taxTotal, int $captured, int $alreadyRefunded, int $alreadyRefundedTax, int $refund): int
    {
        if ($amount <= 0 || $taxTotal <= 0 || $captured <= 0 || $refund <= 0) {
            return 0;
        }

        // The tax actually collected: all of it, or its share of a partial capture.
        $taxCollected = $captured >= $amount ? $taxTotal : intdiv($taxTotal * $captured * 2 + $amount, $amount * 2);
        $taxRemaining = max(0, $taxCollected - $alreadyRefundedTax);

        if ($alreadyRefunded + $refund >= $captured) {
            return $taxRemaining;
        }

        $share = intdiv($refund * $taxCollected * 2 + $captured, $captured * 2);

        return min($share, $taxRemaining);
    }
}
