<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Payments\Enums\Currency;
use App\Payments\Money;
use App\Payments\Tax\TaxLine;
use Carbon\CarbonImmutable;
use Database\Factories\TaxRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One configured tax, e.g. "HST" at 13%.
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
     * "HST (13%)": how this rate reads on a receipt.
     */
    public function label(): string
    {
        return (new TaxLine($this->name, (string) $this->percentage, Money::zero(Currency::CAD)))->label();
    }
}
