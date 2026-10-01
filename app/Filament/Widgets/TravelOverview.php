<?php

namespace App\Filament\Widgets;

use App\Auth\Permission;
use App\Models\TourDeparture;
use App\Models\TripInquiry;
use App\Travel\InquiryStatus;
use App\Travel\Price;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The booking pipeline at a glance, read live from the requests and departures.
 */
class TravelOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission(Permission::ViewTripInquiries) ?? false;
    }

    protected function getHeading(): ?string
    {
        return __('travel.dashboard.heading');
    }

    protected function getStats(): array
    {
        $new = TripInquiry::query()->where('status', InquiryStatus::New)->count();
        $newThisWeek = TripInquiry::query()->where('created_at', '>=', now()->subDays(7))->count();
        $pipeline = (int) TripInquiry::query()->open()->sum('quoted_total_cents');

        $confirmed = TripInquiry::query()
            ->where('status', InquiryStatus::Confirmed)
            ->where('confirmed_at', '>=', now()->subDays(30));

        $departures = TourDeparture::query()
            ->whereBetween('starts_on', [now()->toDateString(), now()->addDays(90)->toDateString()]);
        $seatsTotal = (int) (clone $departures)->sum('seats_total');
        $seatsHeld = (int) (clone $departures)->sum('seats_held');

        return [
            Stat::make(__('travel.dashboard.new'), number_format($new))
                ->description(__('travel.dashboard.new_description', ['count' => $newThisWeek]))
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($new > 0 ? 'warning' : 'success'),
            Stat::make(__('travel.dashboard.pipeline'), Price::format($pipeline))
                ->description(__('travel.dashboard.pipeline_description'))
                ->color('info'),
            Stat::make(__('travel.dashboard.confirmed'), Price::format((int) (clone $confirmed)->sum('quoted_total_cents')))
                ->description(__('travel.dashboard.confirmed_description', ['count' => (clone $confirmed)->count()]))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
            Stat::make(__('travel.dashboard.occupancy'), $seatsTotal > 0 ? round($seatsHeld / $seatsTotal * 100).'%' : '—')
                ->description(__('travel.dashboard.occupancy_description', [
                    'held' => $seatsHeld,
                    'total' => $seatsTotal,
                    'departures' => (clone $departures)->count(),
                ])),
        ];
    }
}
