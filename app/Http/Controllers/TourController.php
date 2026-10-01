<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One tour's page: itinerary, inclusions, dates and the booking request form.
 */
class TourController extends Controller
{
    public function show(Request $request, Tour $tour): View
    {
        abort_unless($tour->is_published, 404);

        $tour->load('destination');

        $departures = $tour->departures()
            ->where('starts_on', '>', now()->toDateString())
            ->get()
            ->each->setRelation('tour', $tour);

        return view('travel.tours.show', [
            'tour' => $tour,
            'departures' => $departures,
            'selectedDeparture' => $request->integer('departure') ?: null,
            'relatedTours' => Tour::query()
                ->published()
                ->ordered()
                ->with('destination')
                ->whereKeyNot($tour->id)
                ->where(fn ($query) => $query->where('style', $tour->style)->orWhere('destination_id', $tour->destination_id))
                ->limit(3)
                ->get(),
        ]);
    }
}
