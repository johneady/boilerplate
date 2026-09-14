<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BusinessOverview;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The panel's landing page: a business overview built from widgets, plus the
 * introduction to John Eady's work shown in a modal that opens itself shortly
 * after arrival and stays reachable from a strip above the widgets.
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
            BusinessOverview::class,
        ];
    }
}
