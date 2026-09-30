<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Ordering\Fulfilment;
use App\Ordering\OrderStatus;
use App\Ordering\Price;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

/**
 * An order placed through the website, and its progress through the kitchen.
 *
 * Created by App\Ordering\PlaceOrder only; staff move it along with
 * advance() and cancel() from the admin panel.
 *
 * @property int $id
 * @property string|null $number
 * @property int|null $user_id
 * @property string $customer_name
 * @property string $customer_email
 * @property string $customer_phone
 * @property Fulfilment $fulfilment
 * @property string|null $delivery_address
 * @property CarbonImmutable $ready_at
 * @property string|null $notes
 * @property OrderStatus $status
 * @property int $subtotal_cents
 * @property int $delivery_fee_cents
 * @property int $total_cents
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'customer_name', 'customer_email', 'customer_phone', 'fulfilment', 'delivery_address',
    'ready_at', 'notes', 'status', 'subtotal_cents', 'delivery_fee_cents', 'total_cents',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use Auditable, HasFactory;

    protected static function booted(): void
    {
        // The customer-facing number is derived from the id, so it can only
        // be written once the row exists.
        static::created(function (Order $order): void {
            $order->number = self::numberFor($order->id);
            $order->saveQuietly();
        });
    }

    /**
     * The number customers quote for the order with this id, e.g. JR-1042.
     */
    public static function numberFor(int $id): string
    {
        return config('ordering.order_number_prefix').(1000 + $id);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fulfilment' => Fulfilment::class,
            'status' => OrderStatus::class,
            'ready_at' => 'immutable_datetime',
            'subtotal_cents' => 'integer',
            'delivery_fee_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    /**
     * The customer's contact details stay out of the audit trail; the trail
     * records who moved the order along, not who ordered.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['customer_name', 'customer_email', 'customer_phone', 'delivery_address', 'notes', 'updated_at'];
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Move the order to its next status. Does nothing once it is finished.
     */
    public function advance(): void
    {
        $next = $this->status->next();

        if ($next !== null) {
            $this->update(['status' => $next]);
        }
    }

    public function cancel(): void
    {
        if ($this->status->canBeCancelled()) {
            $this->update(['status' => OrderStatus::Cancelled]);
        }
    }

    /**
     * The customer's link to follow the order.
     *
     * Signed, so a guest can see their own order without an account and
     * nobody can read another customer's by guessing a number.
     */
    public function trackingUrl(): string
    {
        return URL::signedRoute('orders.show', $this);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * When the order is wanted, in the café's own timezone, as the kitchen
     * says it: "Today, 2:30 pm", "Tomorrow, 9:00 am" or "Mon 6 Oct, 9:00 am".
     */
    public function readyLabel(): string
    {
        $timezone = app(Settings::class)->string(SettingKey::Timezone);
        $readyAt = $this->ready_at->setTimezone($timezone);
        $today = CarbonImmutable::now($timezone);

        $day = match (true) {
            $readyAt->isSameDay($today) => __('Today'),
            $readyAt->isSameDay($today->addDay()) => __('Tomorrow'),
            $readyAt->isSameDay($today->subDay()) => __('Yesterday'),
            default => $readyAt->format('D j M'),
        };

        return __(':day, :time', ['day' => $day, 'time' => $readyAt->format('g:i a')]);
    }

    public function formattedTotal(): string
    {
        return Price::format($this->total_cents);
    }
}
