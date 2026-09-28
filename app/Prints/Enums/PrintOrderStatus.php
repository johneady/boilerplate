<?php

namespace App\Prints\Enums;

/**
 * Where a print order is in the lab, and the only moves it may make.
 *
 * Forward-only by construction, mirroring App\Payments\Enums\PaymentStatus:
 * canTransitionTo() is the single table every status change is checked
 * against, so a double-tapped tablet button or a stale browser tab polling an
 * old action cannot move an order backwards or skip a stage.
 */
enum PrintOrderStatus: string
{
    /** Photos arrived; nobody has looked at them yet. */
    case Received = 'received';

    /** A staff member has sent the photos to a printer. */
    case Printing = 'printing';

    /** Prints are done: bagged for pickup, or packed and posted. */
    case Ready = 'ready';

    /** Picked up or delivered. Final. */
    case Completed = 'completed';

    /** Rejected or abandoned. Final. */
    case Cancelled = 'cancelled';

    /**
     * Whether an order may move from this status to another.
     *
     * Staying put is always allowed: the console polls, and re-sending an
     * order that has not changed must be a no-op, not an error.
     */
    public function canTransitionTo(self $next): bool
    {
        if ($next === $this) {
            return true;
        }

        return in_array($next, match ($this) {
            self::Received => [self::Printing, self::Cancelled],
            self::Printing => [self::Ready, self::Cancelled],
            self::Ready => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        }, true);
    }

    /**
     * The statuses the fulfillment console still owes work on, oldest first.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Received, self::Printing, self::Ready];
    }

    /**
     * Whether this status belongs to the console's working queue rather than
     * its history view.
     */
    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Waiting',
            self::Printing => 'Printing',
            self::Ready => 'Ready',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Received => 'amber',
            self::Printing => 'sky',
            self::Ready => 'emerald',
            self::Completed => 'zinc',
            self::Cancelled => 'rose',
        };
    }
}
