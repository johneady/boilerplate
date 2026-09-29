<?php

namespace App\Http\Controllers;

use App\Models\Perfume;
use App\Perfumes\CommunityStats;
use App\Perfumes\Enums\Family;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(CommunityStats $stats): View
    {
        $familyCounts = Perfume::query()
            ->whereNotNull('family')
            ->selectRaw('family, COUNT(*) as total')
            ->groupBy('family')
            ->pluck('total', 'family');

        return view('welcome', [
            'members' => $stats->members(),
            'perfumeCount' => $stats->perfumes(),
            'monthViews' => $stats->views(now()->toImmutable()->startOfDay()->subDays(29)),
            'mostFollowed' => $stats->mostFollowed(6),
            'recentlyAdded' => Perfume::query()->with('brand')->withCount('followers')->latest('created_at')->latest('id')->limit(3)->get(),
            'families' => collect(Family::cases())
                ->map(fn (Family $family): array => ['family' => $family, 'count' => (int) ($familyCounts[$family->value] ?? 0)])
                ->filter(fn (array $entry): bool => $entry['count'] > 0)
                ->values(),
        ]);
    }
}
