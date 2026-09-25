<?php

namespace App\Payments;

use App\Payments\Enums\Currency;
use InvalidArgumentException;
use NumberFormatter;

/**
 * An amount of money: integer minor units plus the currency they are in.
 *
 * Integers throughout, never floats. 0.1 + 0.2 is not 0.3 in binary floating
 * point, and a rounding error in a payment total is a real cent charged or
 * refunded wrongly -- so every calculation here is integer arithmetic, and
 * the one conversion to a decimal string (PayPal's wire format) is done by
 * string formatting rather than division.
 *
 * Immutable: every operation returns a new instance, so an amount handed to
 * a gateway cannot be changed underneath it by the caller.
 */
final readonly class Money
{
    public function __construct(
        public int $amount,
        public Currency $currency,
    ) {}

    /**
     * An amount in minor units.
     */
    public static function of(int $amount, Currency $currency): self
    {
        return new self($amount, $currency);
    }

    /**
     * Nothing, in the given currency.
     */
    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    /**
     * Parse a decimal string such as "12.34" -- PayPal's wire format, and what
     * a customer types into an amount field -- into minor units.
     *
     * Parsed as a string rather than through (float), so "0.29" is 29 cents
     * rather than 28.999999... truncated to 28. More decimal places than the
     * currency carries is rejected, not rounded: an amount the customer did
     * not type must never be the one charged.
     */
    public static function fromDecimal(string $value, Currency $currency): self
    {
        $value = trim($value);

        if (preg_match('/^(-)?(\d+)(?:\.(\d+))?$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException("[{$value}] is not a decimal amount.");
        }

        $fraction = $matches[3] ?? '';

        if (strlen($fraction) > $currency->decimals()) {
            throw new InvalidArgumentException("[{$value}] has more decimal places than {$currency->value} allows.");
        }

        $minor = (int) ($matches[2].str_pad($fraction, $currency->decimals(), '0'));

        return new self($matches[1] === '-' ? -$minor : $minor, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->amount, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    /**
     * Split this amount across weights by the largest-remainder method.
     *
     * The parts always sum to exactly this amount -- the property a naive
     * per-part rounding loses. Splitting a $0.10 refund of tax across three
     * equal tax lines gives 4 + 3 + 3, never 3 + 3 + 3 with a cent vanished.
     * Ties go to the earliest weight, so the result is deterministic.
     *
     * @param  array<array-key, int>  $weights  Non-negative; keys are preserved.
     * @return array<array-key, self>
     */
    public function allocate(array $weights): array
    {
        $total = array_sum($weights);

        if ($weights === [] || $total <= 0) {
            throw new InvalidArgumentException('Allocation needs at least one positive weight.');
        }

        $sign = $this->amount < 0 ? -1 : 1;
        $absolute = abs($this->amount);

        $parts = [];
        $remainders = [];

        foreach ($weights as $key => $weight) {
            if ($weight < 0) {
                throw new InvalidArgumentException('Allocation weights must not be negative.');
            }

            $parts[$key] = intdiv($absolute * $weight, $total);
            $remainders[$key] = ($absolute * $weight) % $total;
        }

        $leftover = $absolute - array_sum($parts);

        // Largest remainder first, ties broken by position, so the same
        // inputs always produce the same split.
        $order = [];

        foreach (array_keys($remainders) as $position => $key) {
            $order[] = ['key' => $key, 'remainder' => $remainders[$key], 'position' => $position];
        }

        usort($order, fn (array $a, array $b): int => [$b['remainder'], $a['position']] <=> [$a['remainder'], $b['position']]);

        foreach (array_slice($order, 0, $leftover) as $entry) {
            $parts[$entry['key']]++;
        }

        return array_map(fn (int $part): self => new self($sign * $part, $this->currency), $parts);
    }

    /**
     * The amount as a plain decimal string, e.g. "1234.50" -- PayPal's format.
     */
    public function toDecimal(): string
    {
        $decimals = $this->currency->decimals();
        $digits = str_pad((string) abs($this->amount), $decimals + 1, '0', STR_PAD_LEFT);

        $whole = substr($digits, 0, strlen($digits) - $decimals);
        $fraction = substr($digits, -$decimals);

        return ($this->amount < 0 ? '-' : '').$whole.($decimals > 0 ? '.'.$fraction : '');
    }

    /**
     * The amount formatted for a person, e.g. "CA$1,234.50".
     *
     * Formatted from the decimal string rather than amount / 100, so the
     * display can never disagree with the amount sent to a gateway. The float
     * NumberFormatter requires is used for display only and is exact for any
     * amount a payment can hold (well inside 2^53 minor units).
     *
     * The locale is a parameter rather than read from the application, so
     * this stays usable from unit tests, which run without a container.
     */
    public function format(string $locale = 'en'): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency((float) $this->toDecimal(), $this->currency->value);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException("Cannot combine {$this->currency->value} with {$other->currency->value}.");
        }
    }
}
