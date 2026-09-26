<?php

namespace App\Filament\Forms;

use App\Payments\Enums\Currency;
use App\Payments\Money;
use App\Payments\PaymentManager;
use Closure;
use Filament\Forms\Components\TextInput;

/**
 * A text field for an amount of money, stored as integer minor units.
 *
 * The administrator types dollars ("25" or "25.50"); the column holds cents.
 * Converted through Money on both sides, never through a float, so what is
 * typed is exactly what is stored.
 */
class MoneyInput extends TextInput
{
    public static function make(?string $name = null, ?Currency $currency = null): static
    {
        $currency ??= app(PaymentManager::class)->currency();

        // The decimal places the currency itself carries, so a zero- or
        // three-decimal currency is not silently rejected by a hardcoded
        // two (see Currency::decimals()).
        $decimals = $currency->decimals();

        return parent::make($name)
            ->prefix($currency->value)
            ->inputMode('decimal')
            ->rule('regex:/^\d{1,9}('.($decimals > 0 ? '\.\d{1,'.$decimals.'}' : '').')?$/')
            ->formatStateUsing(fn (mixed $state): ?string => is_numeric($state) ? Money::of((int) $state, $currency)->toDecimal() : null)
            ->dehydrateStateUsing(fn (mixed $state): ?int => filled($state) ? Money::fromDecimal((string) $state, $currency)->amount : null);
    }

    /**
     * Require a filled amount to be greater than zero.
     *
     * The rule runs on the dehydrated state, so the value under validation is
     * integer minor units; blankness is left to ->required()/->nullable() at
     * the call site, which is why this passes rather than fails on empty.
     */
    public function positive(): static
    {
        return $this->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (filled($value) && is_numeric($value) && (float) $value <= 0) {
                $fail(__('payments.links.amount_positive'));
            }
        });
    }
}
