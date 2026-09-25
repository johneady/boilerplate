<?php

namespace App\Payments\Enums;

/**
 * Where a dispute (a chargeback, or a PayPal claim) stands.
 *
 * Evidence is submitted in the gateway's own dashboard; this application
 * tracks the dispute and tells operators about it. A dispute may move back and
 * forth between needing a response and being reviewed (PayPal asks for more
 * information), but once decided it stays decided.
 *
 * Plain English only: translate at the point of display.
 */
enum DisputeStatus: string
{
    /** The merchant must respond, with evidence, by evidence_due_by. */
    case NeedsResponse = 'needs_response';

    /** Evidence is in, or the other party must respond; awaiting a decision. */
    case UnderReview = 'under_review';

    /** Decided for the merchant, withdrawn, or closed with nothing lost. Final. */
    case Won = 'won';

    /** Decided for the customer: the money is gone. Final. */
    case Lost = 'lost';

    public function canTransitionTo(self $next): bool
    {
        if ($next === $this) {
            return true;
        }

        return in_array($next, match ($this) {
            self::NeedsResponse => [self::UnderReview, self::Won, self::Lost],
            self::UnderReview => [self::NeedsResponse, self::Won, self::Lost],
            self::Won, self::Lost => [],
        }, true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::NeedsResponse, self::UnderReview], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::NeedsResponse => 'Needs response',
            self::UnderReview => 'Under review',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    /**
     * The badge colour in the admin panel. Must be registered on the panel.
     */
    public function color(): string
    {
        return match ($this) {
            self::NeedsResponse => 'danger',
            self::UnderReview => 'warning',
            self::Won => 'success',
            self::Lost => 'zinc',
        };
    }
}
