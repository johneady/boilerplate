<?php

namespace App\Payments\Enums;

/**
 * Where a payment is in its life, and the only moves it may make.
 *
 * Forward-only by construction: canTransitionTo() is the single table every
 * status change is checked against, so a late or out-of-order event -- a
 * "pending" webhook delivered after the payment succeeded -- cannot move a
 * payment backwards. An event asking for an illegal move is logged and
 * ignored by App\Payments\Actions\ReconcilePayment, never applied.
 */
enum PaymentStatus: string
{
    /** Created; the customer has not finished the hosted checkout. */
    case Pending = 'pending';

    /** Funds are held but not taken; awaiting capture or void. */
    case Authorized = 'authorized';

    /** Funds taken, nothing refunded. */
    case Succeeded = 'succeeded';

    case PartiallyRefunded = 'partially_refunded';

    case Refunded = 'refunded';

    /** The gateway declined the payment. Final. */
    case Failed = 'failed';

    /** An authorization released without capture, or a checkout cancelled. Final. */
    case Voided = 'voided';

    /** Abandoned checkout, or an authorization that lapsed. Final. */
    case Expired = 'expired';

    /**
     * Whether a payment may move from this status to another.
     *
     * Staying in the same status is always allowed -- reconciling a payment
     * that has not changed must be a no-op, not an error.
     */
    public function canTransitionTo(self $next): bool
    {
        if ($next === $this) {
            return true;
        }

        // Pending and Authorized may jump straight to a refunded status: the
        // first read of a payment whose webhooks were lost can find the charge
        // AND a dashboard refund on it at once.
        return in_array($next, match ($this) {
            self::Pending => [self::Authorized, self::Succeeded, self::PartiallyRefunded, self::Refunded, self::Failed, self::Voided, self::Expired],
            self::Authorized => [self::Succeeded, self::PartiallyRefunded, self::Refunded, self::Voided, self::Expired, self::Failed],
            self::Succeeded => [self::PartiallyRefunded, self::Refunded],
            self::PartiallyRefunded => [self::Refunded],
            self::Refunded, self::Failed, self::Voided, self::Expired => [],
        }, true);
    }

    /**
     * Whether money has been taken, whatever has been refunded since.
     */
    public function isPaid(): bool
    {
        return in_array($this, [self::Succeeded, self::PartiallyRefunded, self::Refunded], true);
    }

    /**
     * Whether nothing further can happen to this payment.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Refunded, self::Failed, self::Voided, self::Expired], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Authorized => 'Authorized',
            self::Succeeded => 'Paid',
            self::PartiallyRefunded => 'Partially refunded',
            self::Refunded => 'Refunded',
            self::Failed => 'Failed',
            self::Voided => 'Voided',
            self::Expired => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Authorized => 'info',
            self::Succeeded => 'success',
            self::PartiallyRefunded, self::Refunded => 'warning',
            self::Failed => 'danger',
            self::Voided, self::Expired => 'zinc',
        };
    }
}
