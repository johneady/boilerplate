<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Models\Tour;
use App\Travel\TourStyle;
use Illuminate\View\View;

/**
 * The home page: a search that lands on the tour finder, the bestselling tours
 * and the destinations grid.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('welcome', [
            'featuredTours' => Tour::query()
                ->published()
                ->where('is_featured', true)
                ->ordered()
                ->with(['destination', 'bookableDepartures'])
                ->limit(3)
                ->get(),
            'destinations' => Destination::query()
                ->ordered()
                ->withCount(['tours' => fn ($query) => $query->published()])
                ->get(),
            'styles' => TourStyle::cases(),
        ]);
    }
}
