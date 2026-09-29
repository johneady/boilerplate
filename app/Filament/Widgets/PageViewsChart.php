<?php

namespace App\Filament\Widgets;

use App\Auth\Permission;
use App\Perfumes\CommunityStats;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class PageViewsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 2;

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission(Permission::ManagePerfumes) ?? false;
    }

    public function getHeading(): ?string
    {
        return __('dashboard.views_chart.heading');
    }

    public function getDescription(): ?string
    {
        return __('dashboard.views_chart.description');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = app(CommunityStats::class)->dailyViews(30);
        $settings = app(Settings::class);

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.views_chart.dataset'),
                    'data' => array_values($days),
                    'backgroundColor' => '#a8558e',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => array_map(fn (string $date): string => $settings->formatDate(new CarbonImmutable($date)), array_keys($days)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['ticks' => ['maxTicksLimit' => 8]]],
        ];
    }
}
