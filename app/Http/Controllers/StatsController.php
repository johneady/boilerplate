<?php

namespace App\Http\Controllers;

use App\Perfumes\CommunityStats;
use Illuminate\View\View;

/**
 * The public "open stats" page: follower and usage figures anyone can see,
 * so the community's size can be shown to partners and brands with a link.
 */
class StatsController extends Controller
{
    public function __invoke(CommunityStats $stats): View
    {
        $today = now()->toImmutable()->startOfDay();

        return view('perfumes.stats', [
            'members' => $stats->members(),
            'newMembers' => $stats->newMembersSince($today->subDays(29)),
            'follows' => $stats->follows(),
            'perfumes' => $stats->perfumes(),
            'monthViews' => $stats->views($today->subDays(29)),
            'dailyViews' => $stats->dailyViews(30),
            'growth' => $stats->memberGrowth(12),
            'mostFollowed' => $stats->mostFollowed(10),
            'lastImport' => $stats->lastImport(),
        ]);
    }
}
