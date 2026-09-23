<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Shop\Region;
use Illuminate\View\View;

/**
 * The storefront home page: featured packages and a way into the catalogue.
 */
class HomeController extends Controller
{
    /**
     * Render the home page.
     */
    public function __invoke(): View
    {
        $packages = Package::query()->active()->ordered()->get();

        $featured = $packages->where('is_featured', true)->take(3);

        return view('welcome', [
            // Topped up from the rest of the catalogue so the row is never
            // short just because fewer than three are marked featured.
            'featured' => $featured->concat($packages->diff($featured))->take(3)->values(),
            'packageCount' => $packages->count(),
            'regions' => collect(Region::cases())
                ->map(fn (Region $region): array => [
                    'region' => $region,
                    'count' => $packages->where('region', $region)->count(),
                ])
                ->where('count', '>', 0)
                ->values(),
        ]);
    }
}
