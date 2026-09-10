<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The panel's landing page, which presents an overview of the work John Eady does.
 *
 * The content mirrors the introduction modal on johneady.duckdns.org, rendered
 * inline here rather than as a popup because a dashboard is already the first
 * thing an admin sees -- there is nothing to interrupt.
 */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    protected static ?string $title = 'Overview';

    /**
     * The panel registers no widgets, so the base dashboard's widget grid would
     * only render an empty container above the content.
     *
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [];
    }
}
