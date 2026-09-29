<?php

namespace App\Filament\Widgets;

use App\Auth\Permission;
use App\Payments\BusinessMetrics;
use App\Perfumes\CommunityStats;
use App\Settings\Settings;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Followers and usage at a glance: the last 30 days against the 30 before.
 */
class CommunityOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission(Permission::ManagePerfumes) ?? false;
    }

    protected function getHeading(): ?string
    {
        return __('dashboard.community.heading');
    }

    protected function getStats(): array
    {
        $stats = app(CommunityStats::class);
        $today = now()->toImmutable()->startOfDay();
        $monthAgo = $today->subDays(29);
        $twoMonthsAgo = $today->subDays(59);
        $lastImport = $stats->lastImport();

        $newMembers = $stats->newMembersSince($monthAgo);
        $previousMembers = $stats->newMembersSince($twoMonthsAgo, $monthAgo);
        $views = $stats->views($monthAgo);
        $previousViews = $stats->views($twoMonthsAgo, $monthAgo);

        return [
            Stat::make(__('dashboard.community.followers'), number_format($stats->members()))
                ->icon('heroicon-m-users')
                ->chart(array_map(floatval(...), array_values($stats->memberGrowth(12))))
                ->description(__('dashboard.community.followers_help')),
            $this->compared(
                Stat::make(__('dashboard.community.views'), number_format($views))
                    ->icon('heroicon-m-eye')
                    ->chart(array_map(floatval(...), array_values($stats->dailyViews(30)))),
                $views,
                $previousViews,
            ),
            $this->compared(
                Stat::make(__('dashboard.community.new_followers_stat'), number_format($newMembers))
                    ->icon('heroicon-m-user-plus'),
                $newMembers,
                $previousMembers,
            ),
            Stat::make(__('dashboard.community.perfumes'), number_format($stats->perfumes()))
                ->icon('heroicon-m-sparkles')
                ->description($lastImport
                    ? __('dashboard.community.last_refresh', ['date' => app(Settings::class)->formatDate($lastImport->finished_at)])
                    : __('dashboard.community.never_refreshed')),
        ];
    }

    private function compared(Stat $stat, int $current, int $previous): Stat
    {
        $change = BusinessMetrics::percentChange($current, $previous);

        if ($change === null || $change === 0.0) {
            return $stat->description(__('dashboard.community.vs_previous'));
        }

        $rising = $change > 0;

        return $stat
            ->description(__($rising ? 'dashboard.community.up' : 'dashboard.community.down', [
                'percent' => number_format(abs($change), 1),
            ]))
            ->descriptionIcon($rising ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down', IconPosition::After)
            ->color($rising ? 'success' : 'danger');
    }
}
