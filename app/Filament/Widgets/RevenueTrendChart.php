<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

/**
 * Revenue against expenses over the last twelve months, as a soft filled line
 * chart. Sample data while the dashboard serves as a work sample.
 */
class RevenueTrendChart extends ChartWidget
{
    protected int|string|array $columnSpan = 2;

    protected ?string $heading = 'Revenue vs expenses';

    protected ?string $description = 'Rolling 12 months';

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => [31200, 34800, 33600, 37400, 36100, 39800, 41200, 40600, 43900, 42100, 45800, 48650],
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.08)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Expenses',
                    'data' => [19800, 20400, 21600, 20900, 22300, 21800, 23100, 24600, 23800, 25100, 24700, 26200],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.05)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => ['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => ['usePointStyle' => true],
                ],
            ],
            'scales' => [
                'y' => [
                    'grid' => ['color' => 'rgba(113, 113, 122, 0.1)'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}
