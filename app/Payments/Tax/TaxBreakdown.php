<?php

namespace App\Payments\Tax;

use App\Payments\Money;

/**
 * A subtotal, the taxes charged on it, and the total the customer pays.
 */
final readonly class TaxBreakdown
{
    /**
     * @param  list<TaxLine>  $lines
     */
    public function __construct(
        public Money $subtotal,
        public array $lines,
    ) {}

    /**
     * The sum of every tax line.
     */
    public function taxTotal(): Money
    {
        return array_reduce(
            $this->lines,
            fn (Money $carry, TaxLine $line): Money => $carry->add($line->amount),
            Money::zero($this->subtotal->currency),
        );
    }

    /**
     * What the customer is charged: subtotal plus every tax.
     */
    public function total(): Money
    {
        return $this->subtotal->add($this->taxTotal());
    }

    /**
     * @return list<array{name: string, percentage: string, amount: int}>
     */
    public function linesToArray(): array
    {
        return array_map(fn (TaxLine $line): array => $line->toArray(), $this->lines);
    }
}
