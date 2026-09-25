<?php

use App\Payments\Enums\Currency;
use App\Payments\Money;
use App\Payments\Tax\TaxCalculator;

function taxOn(int $subtotal, array $rates): array
{
    $breakdown = (new TaxCalculator)->calculate(Money::of($subtotal, Currency::CAD), $rates);

    return [
        'lines' => array_map(fn ($line): int => $line->amount->amount, $breakdown->lines),
        'tax' => $breakdown->taxTotal()->amount,
        'total' => $breakdown->total()->amount,
    ];
}

test('a single rate is added on top of the subtotal', function () {
    expect(taxOn(10000, [['name' => 'HST', 'percentage' => '13.000']]))
        ->toBe(['lines' => [1300], 'tax' => 1300, 'total' => 11300]);
});

test('each tax is rounded on its own, the way a Canadian receipt shows GST and PST', function () {
    // 5% and 7% of $10.05 are $0.5025 and $0.7035: 50 and 70 cents. Taking
    // 12% of the total and splitting it afterwards would give a different pair.
    expect(taxOn(1005, [['name' => 'GST', 'percentage' => '5'], ['name' => 'PST', 'percentage' => '7']]))
        ->toBe(['lines' => [50, 70], 'tax' => 120, 'total' => 1125]);
});

test('a three-decimal rate is applied exactly', function () {
    // QST 9.975% of $1.00 is 9.975 cents, which rounds up to 10.
    expect(taxOn(100, [['name' => 'QST', 'percentage' => '9.975']]))
        ->toBe(['lines' => [10], 'tax' => 10, 'total' => 110]);
});

test('half a cent rounds up', function () {
    // 5% of 10 cents is exactly half a cent.
    expect(taxOn(10, [['name' => 'GST', 'percentage' => '5']])['tax'])->toBe(1);
});

test('no rates means no tax', function () {
    expect(taxOn(4999, []))->toBe(['lines' => [], 'tax' => 0, 'total' => 4999]);
});

test('a percentage is read as integer thousandths of a percent', function (string $percentage, int $thousandths) {
    expect(TaxCalculator::thousandths($percentage))->toBe($thousandths);
})->with([
    ['13', 13000],
    ['13.000', 13000],
    ['9.975', 9975],
    ['0.5', 500],
    ['100', 100000],
]);

test('a percentage that is not a sane rate is refused before it can reach a total', function (string $percentage) {
    TaxCalculator::thousandths($percentage);
})->with(['9.9751', '100.001', '-5', 'thirteen', ''])->throws(InvalidArgumentException::class);
