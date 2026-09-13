<?php

namespace App\Filament\Widgets;

use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * A snapshot of the trading month for the business owner. Figures are sample
 * data while the dashboard serves as a work sample.
 */
class BusinessOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'This month at a glance';

    protected ?string $description = 'Sample figures for demonstration';

    protected function getStats(): array
    {
        return [
            Stat::make('Revenue this month', '$48,650')
                ->description('12.4% increase vs last month')
                ->descriptionIcon('heroicon-m-arrow-trending-up', IconPosition::After)
                ->descriptionColor('success')
                ->color('success')
                ->chart([28, 31, 27, 35, 33, 38, 41, 39, 44, 43, 46, 48])
                ->icon('heroicon-m-currency-dollar'),
            Stat::make('New orders', '342')
                ->description('4.6% increase vs last month')
                ->descriptionIcon('heroicon-m-arrow-trending-up', IconPosition::After)
                ->descriptionColor('success')
                ->color('primary')
                ->chart([22, 26, 24, 29, 27, 31, 33, 30, 35, 33, 36, 38])
                ->icon('heroicon-m-shopping-bag'),
            Stat::make('Active customers', '1,284')
                ->description('8.2% increase vs last quarter')
                ->descriptionIcon('heroicon-m-arrow-trending-up', IconPosition::After)
                ->descriptionColor('success')
                ->color('warning')
                ->chart([820, 874, 913, 968, 1_024, 1_067, 1_121, 1_158, 1_203, 1_231, 1_262, 1_284])
                ->icon('heroicon-m-users'),
            Stat::make('Refund rate', '1.9%')
                ->description('0.6% decrease vs last month')
                ->descriptionIcon('heroicon-m-arrow-trending-down', IconPosition::After)
                ->descriptionColor('danger')
                ->color('danger')
                ->chart([3.4, 3.1, 3.3, 2.9, 2.7, 2.8, 2.5, 2.4, 2.2, 2.1, 2.0, 1.9])
                ->icon('heroicon-m-receipt-percent'),
        ];
    }
}
