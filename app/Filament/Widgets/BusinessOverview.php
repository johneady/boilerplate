<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\Enquiry;
use App\Models\Vehicle;
use App\Voltiva\EnquiryStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Voltiva at a glance: the enquiry pipeline and what is live on the site,
 * read from the database rather than the boilerplate's sample figures.
 */
class BusinessOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Voltiva at a glance';

    protected ?string $description = 'Live figures from enquiries and the website';

    protected function getStats(): array
    {
        $total = Enquiry::query()->count();
        $closed = [EnquiryStatus::Won, EnquiryStatus::Lost];
        $open = Enquiry::query()->whereNotIn('status', $closed)->count();
        $new = Enquiry::query()->where('status', EnquiryStatus::New)->count();
        $thisWeek = Enquiry::query()->where('created_at', '>=', now()->subDays(7))->count();
        $finance = Enquiry::query()->where('finance_interest', true)->count();
        $won = Enquiry::query()->where('status', EnquiryStatus::Won)->count();

        // The last 14 days of enquiries, oldest first, for the sparkline.
        $daily = collect(range(13, 0))
            ->map(fn (int $daysAgo): int => Enquiry::query()->whereDate('created_at', now()->subDays($daysAgo)->toDateString())->count())
            ->all();

        return [
            Stat::make('Enquiries this week', (string) $thisWeek)
                ->description($new.' waiting for first contact')
                ->descriptionColor($new > 0 ? 'warning' : 'success')
                ->color('primary')
                ->chart($daily)
                ->icon('heroicon-m-inbox-arrow-down'),
            Stat::make('Open enquiries', (string) $open)
                ->description($won.' sold so far')
                ->descriptionColor('success')
                ->color('success')
                ->icon('heroicon-m-user-group'),
            Stat::make('Interested in finance', $total > 0 ? round($finance / $total * 100).'%' : '—')
                ->description($finance.' of '.$total.' enquiries')
                ->color('warning')
                ->icon('heroicon-m-banknotes'),
            Stat::make('Cars on the site', (string) Vehicle::query()->published()->count())
                ->description(Article::query()->published()->count().' articles published')
                ->color('gray')
                ->icon('heroicon-m-bolt'),
        ];
    }
}
