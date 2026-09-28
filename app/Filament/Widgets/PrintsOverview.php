<?php

namespace App\Filament\Widgets;

use App\Auth\Permission;
use App\Models\PrintOrder;
use App\Prints\PrintPricing;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * The photo counter at a glance: what is owed, what is moving, what the week
 * took.
 *
 * The lab's answer to "press an icon and see who's sent photos": waiting and
 * ready counts are the two numbers a manager actually acts on, prints today
 * is the printers' load, and the week's takings cover both the counter and
 * the mail-out.
 */
class PrintsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * Not polled: the console polls, and the dashboard re-renders whenever a
     * staff member returns to it.
     */
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission(Permission::ViewPrintOrders) ?? false;
    }

    protected function getStats(): array
    {
        $counts = PrintOrder::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->whereIn('status', ['received', 'printing', 'ready'])
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $printsToday = (int) PrintOrder::query()
            ->whereDate('created_at', today())
            ->withSum('items as prints', 'quantity')
            ->get()
            ->sum('prints');

        $takenThisWeek = PrintPricing::money((int) PrintOrder::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [now()->startOfWeek(), now()])
            ->sum('prints_total_cents'));

        return [
            Stat::make(__('print-orders.overview.waiting'), (string) (int) ($counts['received'] ?? 0))
                ->description(__('print-orders.overview.heading'))
                ->descriptionIcon('heroicon-m-clock', IconPosition::Before)
                ->color('amber'),
            Stat::make(__('print-orders.overview.printing'), (string) (int) ($counts['printing'] ?? 0))
                ->description(__('print-orders.overview.heading'))
                ->descriptionIcon('heroicon-m-printer', IconPosition::Before)
                ->color('sky'),
            Stat::make(__('print-orders.overview.ready'), (string) (int) ($counts['ready'] ?? 0))
                ->description(__('print-orders.overview.heading'))
                ->descriptionIcon('heroicon-m-check-circle', IconPosition::Before)
                ->color('emerald'),
            Stat::make(__('print-orders.overview.prints_today'), number_format($printsToday))
                ->description(__('print-orders.overview.heading'))
                ->descriptionIcon('heroicon-m-photo', IconPosition::Before)
                ->color('primary'),
            Stat::make(__('print-orders.overview.taken_this_week'), $takenThisWeek)
                ->description(__('print-orders.overview.heading'))
                ->descriptionIcon('heroicon-m-banknotes', IconPosition::Before)
                ->color('primary'),
        ];
    }
}
