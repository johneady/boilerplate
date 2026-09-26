<?php

namespace App\Payments\Data;

use App\Payments\Money;

/**
 * One period's figures, as the summary email reports them.
 *
 * Computed when the summary is due and carried to the notification whole,
 * so a queued email renders the figures of the moment it was sent rather
 * than re-reading a database that has moved on. Money figures are null
 * while payments are switched off.
 */
final readonly class BusinessSummary
{
    /**
     * @param  'weekly'|'monthly'  $frequency
     * @param  array<'disputes'|'past_due'|'holds'|'messages', int>  $attention  See BusinessMetrics::attentionCounts().
     */
    public function __construct(
        public string $frequency,
        public string $periodLabel,
        public ?Money $netRevenue,
        public ?Money $previousNetRevenue,
        public ?Money $refunded,
        public int $newCustomers,
        public ?int $activeSubscriptions,
        public ?Money $monthlyRecurringRevenue,
        public array $attention,
    ) {}

    /**
     * Whether anything happened worth an email.
     *
     * Subscriptions carrying on are not news: a period with no sale, no
     * refund, no sign-up and nothing waiting on anyone sends nothing.
     */
    public function hasActivity(): bool
    {
        return ($this->netRevenue !== null && ! $this->netRevenue->isZero())
            || ($this->refunded !== null && ! $this->refunded->isZero())
            || $this->newCustomers > 0
            || array_sum($this->attention) > 0;
    }
}
