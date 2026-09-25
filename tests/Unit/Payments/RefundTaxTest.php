<?php

use App\Payments\Tax\RefundTax;

test('a partial refund returns tax in proportion to what it refunds', function () {
    // $113.00 = $100 + $13 HST. Refunding $56.50 returns half the tax.
    expect(RefundTax::share(amount: 11300, taxTotal: 1300, captured: 11300, alreadyRefunded: 0, alreadyRefundedTax: 0, refund: 5650))->toBe(650);
});

test('the refund that completes a payment returns exactly the tax left, whatever rounding did before', function () {
    $taxShares = [];
    $refunded = 0;
    $refundedTax = 0;

    // Three awkward partial refunds of a $10.00 + $1.30 payment.
    foreach ([333, 333, 464] as $refund) {
        $share = RefundTax::share(amount: 1130, taxTotal: 130, captured: 1130, alreadyRefunded: $refunded, alreadyRefundedTax: $refundedTax, refund: $refund);
        $taxShares[] = $share;
        $refunded += $refund;
        $refundedTax += $share;
    }

    expect($refunded)->toBe(1130)
        ->and(array_sum($taxShares))->toBe(130);
});

test('only the tax actually collected can be returned after a partial capture', function () {
    // A $113.00 hold captured at $56.50 collected half the tax.
    expect(RefundTax::share(amount: 11300, taxTotal: 1300, captured: 5650, alreadyRefunded: 0, alreadyRefundedTax: 0, refund: 5650))->toBe(650);
});

test('an untaxed payment refunds no tax', function () {
    expect(RefundTax::share(amount: 5000, taxTotal: 0, captured: 5000, alreadyRefunded: 0, alreadyRefundedTax: 0, refund: 5000))->toBe(0);
});
