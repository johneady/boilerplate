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
 * @property string $percentage
 * @property bool $is_active
 * @property int $sort_order
 * @property array<string, mixed>|null $gateway_refs
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'percentage', 'is_active', 'sort_order'])]
class TaxRate extends Model
{
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
     * @return array{name: string, percentage: string}
     */
    public function toCalculatorRate(): array
    {
        return ['name' => $this->name, 'percentage' => (string) $this->percentage];
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
            'name' => $rates->pluck('name')->implode(' + '),
            'percentage' => sprintf('%d.%03d', intdiv($thousandths, 1000), $thousandths % 1000),
        ];
    }
}
