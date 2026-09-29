<?php

namespace App\Filament\Widgets;

use App\Auth\Permission;
use App\Perfumes\CommunityStats;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class FollowerGrowthChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission(Permission::ManagePerfumes) ?? false;
    }

    public function getHeading(): ?string
    {
        return __('dashboard.growth_chart.heading');
    }

    public function getDescription(): ?string
    {
        return __('dashboard.growth_chart.description');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $weeks = app(CommunityStats::class)->memberGrowth(12);
        $settings = app(Settings::class);

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.growth_chart.dataset'),
                    'data' => array_values($weeks),
                    'borderColor' => '#8c3f74',
                    'backgroundColor' => 'rgba(168, 85, 142, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                    'borderWidth' => 2,
                    'pointRadius' => 3,
                ],
            ],
            'labels' => array_map(fn (string $date): string => $settings->formatDate(new CarbonImmutable($date)), array_keys($weeks)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['ticks' => ['maxTicksLimit' => 4, 'maxRotation' => 0]]],
        ];
    }
}
