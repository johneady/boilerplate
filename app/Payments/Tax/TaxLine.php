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
     * @param  string|null  $registrationNumber  The business's registration for this tax, printed beside it on receipts.
     */
    public function __construct(
        public string $name,
        public string $percentage,
        public Money $amount,
        public ?string $registrationNumber = null,
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
     * The registration number is included only when there is one, so a line
     * without it snapshots exactly as lines always have.
     *
     * @return array{name: string, percentage: string, amount: int, registration_number?: string}
     */
    public function toArray(): array
    {
        $line = [
            'name' => $this->name,
            'percentage' => $this->percentage,
            'amount' => $this->amount->amount,
        ];

        if ($this->registrationNumber !== null) {
            $line['registration_number'] = $this->registrationNumber;
        }

        return $line;
    }

    /**
     * @param  array{name: string, percentage: string, amount: int, registration_number?: string|null}  $line
     */
    public static function fromArray(array $line, Currency $currency): self
    {
        return new self(
            $line['name'],
            $line['percentage'],
            Money::of((int) $line['amount'], $currency),
            $line['registration_number'] ?? null,
        );
    }
}
