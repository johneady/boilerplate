<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BusinessOverview;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\TravelOverview;
use App\Filament\Widgets\UpcomingDepartures;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The panel's landing page: the business at a glance, built from widgets that
 * each check the viewer may see them, plus -- outside production only -- the
 * introduction to John Eady's work in a modal that opens itself shortly after
 * arrival and stays reachable from a strip below the widgets.
 */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    protected static ?string $title = 'Overview';

    public function getColumns(): int|array
    {
        return 3;
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            TravelOverview::class,
            UpcomingDepartures::class,
            BusinessOverview::class,
            NeedsAttention::class,
            RevenueChart::class,
        ];
    }
}
