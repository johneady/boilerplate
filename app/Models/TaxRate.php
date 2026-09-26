<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Jobs\SyncPlans;
use App\Payments\Enums\Currency;
use App\Payments\Money;
use App\Payments\Tax\TaxCalculator;
use App\Payments\Tax\TaxLine;
use Carbon\CarbonImmutable;
use Database\Factories\TaxRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One configured tax, e.g. "GST" at 5% or "Sales Tax" at 8.875%.
 *
 * Every active rate applies to every taxable item, in sort order, each on the
 * pre-tax subtotal (App\Payments\Tax\TaxCalculator). Editing a rate never
 * changes a past payment: the lines charged are snapshotted onto the payment
 * when checkout starts.
 *
 * @property int $id
 * @property string $name
 * @property string|null $registration_number
 * @property string $percentage
 * @property bool $is_active
 * @property int $sort_order
 * @property array<string, mixed>|null $gateway_refs
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'registration_number', 'percentage', 'is_active', 'sort_order'])]
class TaxRate extends Model
{
    /**
     * Joins the rate names in a combined rate's name, "GST + QST".
     */
    private const string COMBINED_NAME_SEPARATOR = ' + ';

    /** @use HasFactory<TaxRateFactory> */
    use Auditable, HasFactory;

    protected static function booted(): void
    {
        // Subscription tax is charged by the gateways from copies of these
        // rates, so a change re-syncs the plans.
        // gateway_refs is excluded: the sync itself writes it.
        static::created(fn () => SyncPlans::dispatchForCurrentMode());

        static::updated(function (TaxRate $rate): void {
            if ($rate->wasChanged(['name', 'percentage', 'is_active'])) {
                SyncPlans::dispatchForCurrentMode();
            }
        });

        static::deleted(fn () => SyncPlans::dispatchForCurrentMode());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // A string, never a float: "9.975" must stay exactly that.
            'percentage' => 'decimal:3',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'gateway_refs' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['gateway_refs', 'updated_at'];
    }

    /**
     * Stored trimmed, and null rather than blank, so every reader can test
     * for a registration number with `!== null` alone.
     *
     * @return Attribute<?string, ?string>
     */
    protected function registrationNumber(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => filled($value) ? trim($value) : null,
        );
    }

    /**
     * The rates charged today, in the order they appear on a receipt.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The rate in the shape App\Payments\Tax\TaxCalculator takes.
     *
     * @return array{name: string, percentage: string, registration_number: ?string}
     */
    public function toCalculatorRate(): array
    {
        return [
            'name' => $this->name,
            'percentage' => (string) $this->percentage,
            'registration_number' => $this->registration_number,
        ];
    }

    /**
     * The rates charged today, in the shape the tax calculator takes.
     *
     * Read fresh at every charge rather than cached: charging a stale rate
     * after an admin edit is worse than one small query.
     *
     * @return list<array{name: string, percentage: string, registration_number: ?string}>
     */
    public static function activeCalculatorRates(): array
    {
        return array_values(array_map(
            fn (TaxRate $rate): array => $rate->toCalculatorRate(),
            self::query()->active()->get()->all(),
        ));
    }

    /**
     * "Sales Tax (8.875%)": how this rate reads on a receipt.
     */
    public function label(): string
    {
        return (new TaxLine($this->name, (string) $this->percentage, Money::zero(Currency::CAD)))->label();
    }

    /**
     * The active rates as one line, for a gateway that takes a single tax
     * percentage on a subscription (PayPal): "State Tax + City Tax" at
     * 8.875%. Null when no rate is active.
     *
     * @return array{name: string, percentage: string}|null
     */
    public static function combinedActive(): ?array
    {
        $rates = self::query()->active()->get();

        if ($rates->isEmpty()) {
            return null;
        }

        $thousandths = $rates->sum(fn (TaxRate $rate): int => TaxCalculator::thousandths((string) $rate->percentage));

        return [
            'name' => $rates->pluck('name')->implode(self::COMBINED_NAME_SEPARATOR),
            'percentage' => sprintf('%d.%03d', intdiv($thousandths, 1000), $thousandths % 1000),
        ];
    }

    /**
     * The registration numbers of the rates named in a combined rate's name.
     *
     * PayPal bills a plan one combined tax, recorded on the plan as the name
     * combinedActive() gave it ("GST + QST"). That name is the record of
     * which rates the plan actually charges -- the rates active today may
     * differ -- so a renewal's receipt looks the numbers up by it. Numbers
     * are the rates' current ones: they identify the business's
     * registration, not a term of the charge.
     */
    public static function registrationNumbersFor(string $combinedName): ?string
    {
        $names = explode(self::COMBINED_NAME_SEPARATOR, $combinedName);

        $numbers = self::query()
            ->whereIn('name', $names)
            ->whereNotNull('registration_number')
            ->pluck('registration_number', 'name');

        $inOrder = collect($names)
            ->map(fn (string $name): ?string => $numbers[$name] ?? null)
            ->filter()
            ->unique();

        return $inOrder->isEmpty() ? null : $inOrder->implode(' / ');
    }
}
