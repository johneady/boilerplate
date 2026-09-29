<?php

namespace App\Http\Controllers;

use App\Models\Perfume;
use App\Models\PerfumeView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerfumeController extends Controller
{
    /**
     * A perfume's public page. Each human visit counts toward the day's
     * views; crawlers are left out so the usage figures reflect people.
     */
    public function show(Request $request, Perfume $perfume): View
    {
        if (! $this->isCrawler($request)) {
            PerfumeView::record($perfume);
        }

        $perfume->load('brand')->loadCount('followers');

        $similar = Perfume::query()
            ->with('brand')
            ->withCount('followers')
            ->whereKeyNot($perfume->id)
            ->where('family', $perfume->family)
            ->orderByDesc('followers_count')
            ->limit(4)
            ->get();

        return view('perfumes.show', [
            'perfume' => $perfume,
            'totalViews' => (int) $perfume->dailyViews()->sum('views'),
            'similar' => $similar,
        ]);
    }

    /**
     * Old Lovable-era links (/perfume/{id}) point at the upstream record id.
     * A permanent redirect to the new URL keeps shared links and search
     * rankings working after the move.
     */
    public function legacy(string $externalId): RedirectResponse
    {
        $perfume = Perfume::query()->where('external_id', $externalId)->firstOrFail();

        return redirect()->route('perfumes.show', $perfume, 301);
    }

    private function isCrawler(Request $request): bool
    {
        return preg_match('/bot|crawl|spider|slurp|preview/i', (string) $request->userAgent()) === 1;
    }
}
