<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Shop\Money;
use App\Shop\OrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A completed purchase of one or more packages.
 *
 * @property int $id
 * @property string $reference
 * @property int|null $user_id
 * @property string $customer_name
 * @property string $customer_email
 * @property OrderStatus $status
 * @property int $total_cents
 * @property CarbonImmutable|null $fulfilled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['reference', 'user_id', 'customer_name', 'customer_email', 'status', 'total_cents', 'fulfilled_at'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_cents' => 'integer',
            'fulfilled_at' => 'immutable_datetime',
        ];
    }

    /**
     * Attributes kept out of the audit trail beyond the global denylist.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['updated_at'];
    }

    /**
     * The attribute that identifies an order in a URL.
     */
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * A new, unused order reference.
     *
     * Uppercase letters and digits without the ones people misread over the
     * phone (0/O, 1/I), so a customer quoting it gets it right first time.
     */
    public static function newReference(): string
    {
        do {
            $reference = 'DV-'.Str::upper(Str::password(8, letters: true, numbers: true, symbols: false));
            $reference = strtr($reference, ['0' => '8', 'O' => 'Q', '1' => '7', 'I' => 'J']);
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * The account that placed the order, when the customer was signed in.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The packages bought.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The total, formatted in the shop's currency.
     */
    public function formattedTotal(): string
    {
        return Money::format($this->total_cents);
    }
}
