<?php

namespace App\Concerns;

use App\Models\Payment;
use App\Payments\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The Eloquent half of App\Payments\Contracts\Payable.
 *
 * Use it on a model that implements the contract; the model still decides the
 * amount, tax, capture and acceptance rules itself.
 */
trait IsPayable
{
    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Whether any payment against this payable has taken money.
     */
    public function hasBeenPaid(): bool
    {
        return $this->payments()
            ->whereIn('status', array_map(
                fn (PaymentStatus $status): string => $status->value,
                array_filter(PaymentStatus::cases(), fn (PaymentStatus $status): bool => $status->isPaid()),
            ))
            ->exists();
    }
}
