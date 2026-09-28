<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PrintQueue;
use App\Filament\Widgets\PrintsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The panel's landing page: the photo counter at a glance, built from
 * widgets that each check the viewer may see them, plus -- outside
 * production only -- the introduction to John Eady's work in a modal that
 * opens itself shortly after arrival and stays reachable from a strip below
 * the widgets.
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
            PrintsOverview::class,
            PrintQueue::class,
        ];
    }
}
