<?php

namespace App\Perfumes;

use App\Auth\Role;
use App\Models\Perfume;
use App\Models\PerfumeImport;
use App\Models\PerfumeView;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The follower and usage figures behind the admin dashboard and the public
 * stats page, computed in one place so both always tell the same story.
 *
 * "Members" are registered accounts with the ordinary user role -- the
 * site's followers. Staff accounts are not counted.
 */
class CommunityStats
{
    public function members(): int
    {
        return User::query()->where('role', Role::User)->count();
    }

    public function newMembersSince(CarbonImmutable $since, ?CarbonImmutable $until = null): int
    {
        $query = User::query()->where('role', Role::User)->where('created_at', '>=', $since);

        if ($until !== null) {
            $query->where('created_at', '<', $until);
        }

        return $query->count();
    }

    public function follows(): int
    {
        return DB::table('perfume_follows')->count();
    }

    public function perfumes(): int
    {
        return Perfume::query()->count();
    }

    public function views(CarbonImmutable $from, ?CarbonImmutable $until = null): int
    {
        $query = PerfumeView::query()->where('viewed_on', '>=', $from->toDateString());

        if ($until !== null) {
            $query->where('viewed_on', '<', $until->toDateString());
        }

        return (int) $query->sum('views');
    }

    /**
     * Page views per day for the last $days days, oldest first, with the
     * days nobody visited filled in as zero.
     *
     * @return array<string, int> date => views
     */
    public function dailyViews(int $days): array
    {
        $start = now()->toImmutable()->startOfDay()->subDays($days - 1);

        $recorded = PerfumeView::query()
            ->where('viewed_on', '>=', $start->toDateString())
            ->groupBy('viewed_on')
            ->selectRaw('viewed_on, SUM(views) as total')
            ->pluck('total', 'viewed_on')
            ->mapWithKeys(fn ($total, $date): array => [substr((string) $date, 0, 10) => (int) $total]);

        $series = [];

        for ($day = 0; $day < $days; $day++) {
            $date = $start->addDays($day)->toDateString();
            $series[$date] = $recorded[$date] ?? 0;
        }

        return $series;
    }

    /**
     * Total members at the end of each of the last $weeks weeks, oldest first.
     *
     * @return array<string, int> week-ending date => members
     */
    public function memberGrowth(int $weeks): array
    {
        $end = now()->toImmutable()->endOfDay();
        $series = [];

        for ($week = $weeks - 1; $week >= 0; $week--) {
            $at = $end->subWeeks($week);
            $series[$at->toDateString()] = User::query()
                ->where('role', Role::User)
                ->where('created_at', '<=', $at)
                ->count();
        }

        return $series;
    }

    /**
     * @return Collection<int, Perfume>
     */
    public function mostFollowed(int $limit): Collection
    {
        return Perfume::query()
            ->with('brand')
            ->withCount('followers')
            ->orderByDesc('followers_count')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * The most viewed perfumes since a date, each carrying a period_views
     * attribute.
     *
     * @return Collection<int, Perfume>
     */
    public function mostViewed(CarbonImmutable $since, int $limit): Collection
    {
        return Perfume::query()
            ->with('brand')
            ->withSum(['dailyViews as period_views' => fn ($query) => $query->where('viewed_on', '>=', $since->toDateString())], 'views')
            ->orderByDesc('period_views')
            ->limit($limit)
            ->get();
    }

    public function lastImport(): ?PerfumeImport
    {
        return PerfumeImport::query()->whereNotNull('finished_at')->latest('finished_at')->first();
    }
}
