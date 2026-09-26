<?php

namespace App\Filament\Widgets;

use App\Auth\Permission;
use App\Payments\BusinessMetrics;
use App\Payments\PaymentManager;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The trading month at a glance: revenue, new customers, what recurs and
 * what was refunded, each against the same point last month.
 *
 * "Last month" means last month up to the same day and hour, not all of it:
 * on the 5th, a full previous month would make every month look like a
 * collapse. Shown to anyone who may see payments, while payments are on.
 */
class BusinessOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * Not polled: these are month-level figures, and polling would re-run
     * every query every few seconds for each open dashboard.
     */
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return (auth()->user()?->hasPermission(Permission::ViewPayments) ?? false)
            && app(PaymentManager::class)->enabled();
    }

    protected function getHeading(): ?string
    {
        return __('dashboard.overview.heading');
    }

    protected function getStats(): array
    {
        $metrics = app(BusinessMetrics::class);

        $now = $metrics->now()->utc();
        $thisMonth = $metrics->monthStart();
        $lastMonth = $metrics->monthStart(1);
        // Last month, up to the moment this month has reached -- never past
        // its own end, which on the 31st of a month after a 30-day one would
        // count this month's first day as last month's too.
        $lastMonthSoFar = $lastMonth->add($thisMonth->diff($now))->min($thisMonth);

        $revenue = $metrics->netRevenue($thisMonth, $now);
        $previousRevenue = $metrics->netRevenue($lastMonth, $lastMonthSoFar);
        $customers = $metrics->newCustomers($thisMonth, $now);
        $previousCustomers = $metrics->newCustomers($lastMonth, $lastMonthSoFar);
        $gross = $metrics->grossRevenue($thisMonth, $now);
        $refunded = $metrics->refunded($thisMonth, $now);

        return [
            $this->compared(
                Stat::make(__('dashboard.overview.revenue'), $revenue->format())
                    ->icon('heroicon-m-banknotes')
                    ->chart(array_map(fn (int $minor): float => $minor / 100.0, $metrics->dailyNetRevenue(30))),
                $revenue->amount,
                $previousRevenue->amount,
            ),
            $this->compared(
                Stat::make(__('dashboard.overview.new_customers'), number_format($customers))
                    ->icon('heroicon-m-user-plus'),
                $customers,
                $previousCustomers,
            ),
            Stat::make(__('dashboard.overview.subscriptions'), number_format($metrics->activeSubscriptions()))
                ->icon('heroicon-m-arrow-path')
                ->description(__('dashboard.overview.mrr', ['amount' => $metrics->monthlyRecurringRevenue()->format()])),
            // An amount rather than a rate: a refund this month may be for a
            // sale in an earlier one, so dividing by this month's sales can
            // read over 100% on the 2nd.
            Stat::make(__('dashboard.overview.refunded'), $refunded->format())
                ->icon('heroicon-m-receipt-refund')
                ->description(__('dashboard.overview.taken', ['amount' => $gross->format()])),
        ];
    }

    /**
     * Describe a figure against the same point last month.
     *
     * Green for up and red for down, whatever the figure: every one compared
     * here is one an owner wants to grow.
     */
    private function compared(Stat $stat, int $current, int $previous): Stat
    {
        $change = BusinessMetrics::percentChange($current, $previous);

        if ($change === null) {
            return $stat->description(__('dashboard.overview.no_comparison'));
        }

        if ($change === 0.0) {
            return $stat->description(__('dashboard.overview.level'));
        }

        $rising = $change > 0;

        return $stat
            ->description(__($rising ? 'dashboard.overview.up' : 'dashboard.overview.down', [
                'percent' => number_format(abs($change), 1),
            ]))
            ->descriptionIcon($rising ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down', IconPosition::After)
            ->color($rising ? 'success' : 'danger');
    }
}
