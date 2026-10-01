<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Travel\Region;
use Illuminate\View\View;

/**
 * The public destination pages: the grid of everywhere the agency goes, and one
 * landing page per destination with its published tours.
 */
class DestinationController extends Controller
{
    public function index(): View
    {
        $destinations = Destination::query()
            ->ordered()
            ->withCount(['tours' => fn ($query) => $query->published()])
            ->withMin(['tours' => fn ($query) => $query->published()], 'price_per_person_cents')
            ->get();

        return view('travel.destinations.index', [
            'destinationsByRegion' => $destinations->groupBy(fn (Destination $destination): string => $destination->region->value),
            'regions' => Region::cases(),
        ]);
    }

    public function show(Destination $destination): View
    {
        $tours = $destination->tours()
            ->published()
            ->ordered()
            ->with(['destination', 'bookableDepartures'])
            ->get();

        return view('travel.destinations.show', [
            'destination' => $destination,
            'tours' => $tours,
            'otherDestinations' => Destination::query()->ordered()->whereKeyNot($destination->id)->limit(3)->get(),
        ]);
    }
}
