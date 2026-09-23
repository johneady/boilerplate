<?php

namespace App\Filament\Widgets;

use App\Bakery\InquiryStatus;
use App\Bakery\Money;
use App\Models\MenuItem;
use App\Models\OrderInquiry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The bakery at a glance: what needs a reply, what is due this week, the value
 * of confirmed work and the state of the menu -- read from the database rather
 * than the boilerplate's sample figures.
 */
class BusinessOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'The bakery at a glance';

    protected ?string $description = 'Live figures from order inquiries and the menu';

    protected function getStats(): array
    {
        $awaitingReply = OrderInquiry::query()->awaitingReply()->count();
        $receivedThisWeek = OrderInquiry::query()->where('created_at', '>=', now()->subDays(7))->count();

        $dueThisWeek = OrderInquiry::query()
            ->where('status', InquiryStatus::Confirmed)
            ->whereDate('needed_on', '>=', today())
            ->whereDate('needed_on', '<=', today()->addDays(7))
            ->count();

        // The quote where one was agreed, the menu estimate otherwise.
        $confirmedValue = (int) OrderInquiry::query()
            ->whereIn('status', [InquiryStatus::Confirmed, InquiryStatus::Completed])
            ->whereDate('needed_on', '>=', today()->startOfMonth())
            ->get(['quoted_total_cents', 'estimated_total_cents'])
            ->sum(fn (OrderInquiry $inquiry): int => $inquiry->quoted_total_cents ?? $inquiry->estimated_total_cents);

        // The last 14 days of inquiries, oldest first, for the sparkline.
        $daily = collect(range(13, 0))
            ->map(fn (int $daysAgo): int => OrderInquiry::query()->whereDate('created_at', now()->subDays($daysAgo)->toDateString())->count())
            ->all();

        $onMenu = MenuItem::query()->available()->count();
        $offMenu = MenuItem::query()->where('is_available', false)->count();

        return [
            Stat::make('Waiting for a reply', (string) $awaitingReply)
                ->description($receivedThisWeek.' received in the last 7 days')
                ->descriptionColor($awaitingReply > 0 ? 'warning' : 'success')
                ->color('warning')
                ->chart($daily)
                ->icon('heroicon-m-inbox-arrow-down'),
            Stat::make('Due in the next 7 days', (string) $dueThisWeek)
                ->description('Confirmed orders to bake')
                ->color('primary')
                ->icon('heroicon-m-calendar-days'),
            Stat::make('Confirmed this month', Money::format($confirmedValue))
                ->description('Quoted price, or menu estimate')
                ->descriptionColor('success')
                ->color('success')
                ->icon('heroicon-m-banknotes'),
            Stat::make('On the menu', (string) $onMenu)
                ->description($offMenu > 0 ? $offMenu.' switched off' : 'Everything is available')
                ->color('gray')
                ->icon('heroicon-m-cake'),
        ];
    }
}
