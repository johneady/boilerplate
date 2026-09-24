<?php

use App\Payments\Enums\Currency;
use App\Payments\Money;

test('a decimal amount is parsed into exact minor units', function (string $typed, int $minor) {
    expect(Money::fromDecimal($typed, Currency::CAD)->amount)->toBe($minor);
})->with([
    'whole dollars' => ['12', 1200],
    'one decimal place' => ['12.5', 1250],
    'two decimal places' => ['12.34', 1234],
    // (int) (0.29 * 100) is 28 in floating point; a string parse is exact.
    'a value floats get wrong' => ['0.29', 29],
    'surrounding whitespace' => ['  7.00 ', 700],
    'negative' => ['-3.05', -305],
]);

test('an amount with more decimal places than the currency carries is rejected rather than rounded', function () {
    Money::fromDecimal('1.005', Currency::USD);
})->throws(InvalidArgumentException::class);

test('text that is not an amount is rejected', function (string $typed) {
    Money::fromDecimal($typed, Currency::CAD);
})->with(['abc', '1,000.00', '1e3', '', '.50'])->throws(InvalidArgumentException::class);

test('minor units render as the decimal string PayPal expects', function (int $minor, string $decimal) {
    expect(Money::of($minor, Currency::CAD)->toDecimal())->toBe($decimal);
})->with([
    [0, '0.00'],
    [5, '0.05'],
    [123456, '1234.56'],
    [-250, '-2.50'],
]);

test('an allocation always sums to exactly the amount allocated', function (int $amount, array $weights) {
    $parts = Money::of($amount, Currency::CAD)->allocate($weights);

    expect(array_sum(array_map(fn (Money $part): int => $part->amount, $parts)))->toBe($amount)
        ->and(array_keys($parts))->toBe(array_keys($weights));
})->with([
    'three equal ways' => [10, [1, 1, 1]],
    'uneven weights' => [1000, [650, 350]],
    'more parts than cents' => [2, [1, 1, 1, 1]],
    'keyed weights' => [101, ['gst' => 500, 'pst' => 700]],
    'negative amount' => [-10, [1, 1, 1]],
]);

test('left-over cents go to the largest remainders, earliest first on a tie', function () {
    $parts = Money::of(10, Currency::CAD)->allocate([1, 1, 1]);

    expect(array_map(fn (Money $part): int => $part->amount, $parts))->toBe([4, 3, 3]);
});

test('amounts in different currencies cannot be combined', function () {
    Money::of(100, Currency::CAD)->add(Money::of(100, Currency::USD));
})->throws(InvalidArgumentException::class);

test('an amount is formatted with its currency', function () {
    expect(Money::of(123456, Currency::USD)->format('en_US'))->toBe('$1,234.56')
        ->and(Money::of(123456, Currency::CAD)->format('en_CA'))->toBe('$1,234.56')
        ->and(Money::of(500, Currency::CAD)->format('en_US'))->toBe('CA$5.00');
});
