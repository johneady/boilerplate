<?php

namespace App\Ordering;

/**
 * Where an order is in the kitchen.
 *
 * Orders move forward one step at a time (New -> Preparing -> Ready ->
 * Completed), and can be cancelled until they are completed. next() is the
 * single source of that rule: the admin panel's "advance" button and its
 * tests both read it.
 */
enum OrderStatus: string
{
    case New = 'new';

    case Preparing = 'preparing';

    case Ready = 'ready';

    case Completed = 'completed';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Preparing => 'Preparing',
            self::Ready => 'Ready',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * The Filament colour this status is badged with.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Preparing => 'warning',
            self::Ready => 'success',
            self::Completed => 'gray',
            self::Cancelled => 'danger',
        };
    }

    /**
     * The status an order moves to next, or null once it is finished.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::New => self::Preparing,
            self::Preparing => self::Ready,
            self::Ready => self::Completed,
            self::Completed, self::Cancelled => null,
        };
    }

    /**
     * The label on the button that moves an order out of this status.
     */
    public function advanceLabel(): ?string
    {
        return match ($this) {
            self::New => 'Start preparing',
            self::Preparing => 'Mark ready',
            self::Ready => 'Mark collected',
            self::Completed, self::Cancelled => null,
        };
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::New, self::Preparing, self::Ready], true);
    }

    /**
     * Whether the kitchen still has work to do on the order.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Preparing, self::Ready], true);
    }
}
