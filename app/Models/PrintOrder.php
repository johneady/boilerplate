<?php

namespace App\Models;

use App\Concerns\HasMedia;
use App\Media\HoldsMedia;
use App\Prints\Enums\PrintChannel;
use App\Prints\Enums\PrintOrderStatus;
use App\Prints\Enums\PrintPaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One customer's photo order, wherever it came from.
 *
 * The row is the join between the two halves of the client's problem: the
 * branded phone flow that collected the photos (and, for a mail-out order,
 * the address and the payment), and the tablet console a staff member works
 * through at the counter. Its status is the single column both sides move on.
 *
 * @property int $id
 * @property string $code
 * @property PrintChannel $channel
 * @property int|null $print_location_id
 * @property PrintOrderStatus $status
 * @property PrintPaymentStatus $payment_status
 * @property string $customer_name
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property string|null $mailing_address
 * @property int $prints_total_cents
 * @property int $list_total_cents
 * @property int $savings_cents
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PrintOrder extends Model implements HoldsMedia
{
    use HasMedia;

    /**
     * Written by the wizard and the console rather than mass-assigned: the
     * pricing snapshot and the status transitions are decisions the model's
     * callers make through named methods, not form fields to post in.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'channel' => PrintChannel::class,
        'status' => PrintOrderStatus::class,
        'payment_status' => PrintPaymentStatus::class,
    ];

    /**
     * Generate the customer-facing order code: four unambiguous characters,
     * prefixed so a staff member reading it aloud knows it is ours.
     *
     * Deliberately NOT a sequence: the code is shown to walk-in customers on
     * their own phone screens, and a gapless counter would leak exactly how
     * many orders the lab takes (and let anyone enumerate today's queue by
     * visiting URLs). The alphabet drops 0/O and 1/I so a code survives being
     * read across a counter.
     */
    public static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = 'HPL-'.strtoupper(substr(str_shuffle($alphabet), 0, 4));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * @return HasMany<PrintOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PrintOrderItem::class);
    }

    /**
     * @return BelongsTo<PrintLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(PrintLocation::class, 'print_location_id');
    }

    /**
     * The total number of prints across the order's photos.
     *
     * Uses the loaded relation when the caller already eager-loaded items
     * (the console and the panel's tables both do), so a board of cards is
     * one query rather than one per card.
     */
    public function printCount(): int
    {
        if ($this->relationLoaded('items')) {
            return (int) $this->items->sum('quantity');
        }

        return (int) $this->items()->sum('quantity');
    }

    /**
     * Move the order to the next stage, guarding the transition.
     *
     * A stale tablet tab polling an old screen must not be able to walk an
     * order backwards or skip a stage, so the enum's transition table is the
     * one authority -- an illegal move is refused here rather than at each
     * button.
     */
    public function transitionTo(PrintOrderStatus $next): bool
    {
        if (! $this->status->canTransitionTo($next)) {
            return false;
        }

        $this->status = $next;

        return $this->save();
    }

    /**
     * Limit the query to the statuses the console still owes work on.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', array_map(
            fn (PrintOrderStatus $status): string => $status->value,
            PrintOrderStatus::open(),
        ));
    }
}
