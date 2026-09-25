<?php

namespace App\Payments\Enums;

/**
 * Where a refund is. Pending until the gateway confirms it either way.
 *
 * Forward-only, like PaymentStatus: a refund that succeeded cannot be moved
 * back to pending by a late event.
 */
enum RefundStatus: string
{
    case Pending = 'pending';

    case Succeeded = 'succeeded';

    case Failed = 'failed';

    public function canTransitionTo(self $next): bool
    {
        return $next === $this || ($this === self::Pending && $next !== self::Pending);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Succeeded => 'Refunded',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Succeeded => 'success',
            self::Failed => 'danger',
        };
    }
}
