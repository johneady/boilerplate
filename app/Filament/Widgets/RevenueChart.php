<?php

namespace App\Filament\Widgets;

use App\Auth\Permission;
use App\Payments\BusinessMetrics;
use App\Payments\Money;
use App\Payments\PaymentManager;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Net revenue for each of the last twelve months, this one included.
 */
class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 2;

    protected ?string $maxHeight = '280px';

    /**
     * Not polled: a year of monthly totals does not change by the second.
     */
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return (auth()->user()?->hasPermission(Permission::ViewPayments) ?? false)
            && app(PaymentManager::class)->enabled();
    }

    public function getHeading(): ?string
    {
        return __('dashboard.revenue_chart.heading');
    }

    public function getDescription(): ?string
    {
        return __('dashboard.revenue_chart.description', ['currency' => app(PaymentManager::class)->currency()->value]);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = app(BusinessMetrics::class)->monthlyNetRevenue(12);
        $settings = app(Settings::class);

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.revenue_chart.dataset'),
                    // Decimals, not minor units, so the axis and tooltip read
                    // as money.
                    'data' => array_map(fn (Money $money): float => (float) $money->toDecimal(), array_values($months)),
                ],
            ],
            'labels' => array_map(
                fn (string $month): string => $settings->formatMonth(new CarbonImmutable($month), short: true),
                array_keys($months),
            ),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
