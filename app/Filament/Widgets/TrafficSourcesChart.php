<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

/**
 * Where the last month of visits came from, as a doughnut. Sample data while
 * the dashboard serves as a work sample.
 */
class TrafficSourcesChart extends ChartWidget
{
    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Traffic sources';

    protected ?string $description = 'Visits over the last 30 days';

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'data' => [4182, 2641, 1808, 1219, 894],
                    'backgroundColor' => ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#a1a1aa'],
                    'borderWidth' => 0,
                    'hoverOffset' => 6,
                ],
            ],
            'labels' => ['Organic search', 'Direct', 'Social media', 'Referrals', 'Email'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'cutout' => '64%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => ['usePointStyle' => true],
                ],
            ],
        ];
    }
}
