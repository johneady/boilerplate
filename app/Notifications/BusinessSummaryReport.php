<?php

namespace App\Notifications;

use App\Payments\BusinessMetrics;
use App\Payments\Data\BusinessSummary;
use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The weekly or monthly summary for administrators: what came in, who
 * signed up, and what is waiting on someone.
 *
 * Queued (an explicit opt-in; see .ai/rules/app-notifications.md): it goes to
 * every administrator at once and nothing waits on it. The figures travel
 * with it, computed when it was due, so a delayed send reports the period it
 * was about.
 */
class BusinessSummaryReport extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly BusinessSummary $summary) {}

    public function toMail(object $notifiable): MailMessage
    {
        $summary = $this->summary;

        $title = __($summary->frequency === 'monthly' ? 'Your monthly summary' : 'Your weekly summary');

        $message = $this->mailMessage($title)
            ->greeting($title)
            ->line(__('Here is how :period went at :business.', ['period' => $summary->periodLabel, 'business' => $this->businessName()]));

        if ($summary->netRevenue !== null) {
            $message->line(__('Revenue: :amount, net of refunds (:change).', [
                'amount' => $summary->netRevenue->format(),
                'change' => $this->change(),
            ]));
        }

        if ($summary->refunded !== null && ! $summary->refunded->isZero()) {
            $message->line(__('Refunded: :amount', ['amount' => $summary->refunded->format()]));
        }

        $message->line(trans_choice('summary.new_customers', $summary->newCustomers, ['count' => $summary->newCustomers]));

        if ($summary->activeSubscriptions !== null && $summary->monthlyRecurringRevenue !== null) {
            $message->line(trans_choice('summary.subscriptions', $summary->activeSubscriptions, [
                'count' => $summary->activeSubscriptions,
                'amount' => $summary->monthlyRecurringRevenue->format(),
            ]));
        }

        if ($summary->attention !== []) {
            $message->line(__('Needs attention:'));

            foreach ($summary->attention as $item => $count) {
                $message->line('- '.trans_choice("dashboard.attention.{$item}", $count, ['count' => $count]));
            }
        }

        $panel = Filament::getPanels()['admin'] ?? null;

        if ($panel?->getUrl() !== null) {
            $message->action(__('Open your dashboard'), $panel->getUrl());
        }

        return $message;
    }

    /**
     * Revenue against the period before, in words.
     */
    private function change(): string
    {
        $change = BusinessMetrics::percentChange(
            $this->summary->netRevenue->amount ?? 0,
            $this->summary->previousNetRevenue->amount ?? 0,
        );
        $period = __($this->summary->frequency === 'monthly' ? 'the month before' : 'the week before');

        if ($change === null) {
            return __('nothing to compare with :period', ['period' => $period]);
        }

        if ($change === 0.0) {
            return __('level with :period', ['period' => $period]);
        }

        return __($change > 0 ? ':percent% up on :period' : ':percent% down on :period', [
            'percent' => number_format(abs($change), 1),
            'period' => $period,
        ]);
    }
}
