<?php

namespace App\Models;

use App\Shop\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One package on an order, with the title and price it was sold at.
 *
 * The snapshot columns are the record of the sale: editing or deleting the
 * package afterwards must not rewrite what a customer paid for.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $package_id
 * @property string $package_title
 * @property int $price_cents
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['order_id', 'package_id', 'package_title', 'price_cents'])]
class OrderItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
        ];
    }

    /**
     * The order this line belongs to.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The package sold, or null once it has been deleted.
     *
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * The price paid, formatted in the shop's currency.
     */
    public function formattedPrice(): string
    {
        return Money::format($this->price_cents);
    }
}
