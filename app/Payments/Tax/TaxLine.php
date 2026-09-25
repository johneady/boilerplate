<?php

namespace App\Payments\Tax;

use App\Payments\Enums\Currency;
use App\Payments\Money;

/**
 * One tax charged on a payment, e.g. "Sales Tax 13%: $13.00".
 *
 * Snapshotted onto the payment as JSON when checkout starts, so a receipt
 * always shows the rate that was actually charged, whatever the configured
 * rates have been changed to since.
 */
final readonly class TaxLine
{
    /**
     * @param  string  $percentage  Decimal string, e.g. "8.875". Never a float.
     */
    public function __construct(
        public string $name,
        public string $percentage,
        public Money $amount,
    ) {}

    /**
     * "GST (5%)" or "Sales Tax (8.875%)": the percentage without trailing zeros.
     */
    public function label(): string
    {
        $percentage = str_contains($this->percentage, '.')
            ? rtrim(rtrim($this->percentage, '0'), '.')
            : $this->percentage;

        return "{$this->name} ({$percentage}%)";
    }

    /**
     * @return array{name: string, percentage: string, amount: int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'percentage' => $this->percentage,
            'amount' => $this->amount->amount,
        ];
    }

    /**
     * @param  array{name: string, percentage: string, amount: int}  $line
     */
    public static function fromArray(array $line, Currency $currency): self
    {
        return new self($line['name'], $line['percentage'], Money::of((int) $line['amount'], $currency));
    }
}
