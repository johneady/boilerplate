<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Vehicle;
use App\Voltiva\ArticleTopic;
use App\Voltiva\VehicleCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The car range: the full listing, the L6e and L7e listings, and the one
 * product template every car is rendered with.
 */
class VehicleController extends Controller
{
    /**
     * Every published car.
     */
    public function index(): View
    {
        return view('cars.index', [
            'vehicles' => Vehicle::query()->published()->ordered()->get(),
            'category' => null,
        ]);
    }

    /**
     * The cars of one EU class, introduced by what that class means.
     */
    public function category(string $category): View
    {
        $category = VehicleCategory::from($category);

        return view('cars.index', [
            'vehicles' => Vehicle::query()->published()->where('category', $category)->ordered()->get(),
            'category' => $category,
        ]);
    }

    /**
     * A car's product page.
     *
     * An unpublished car is a 404 for the public but renders for anyone who
     * may edit it, so the panel's preview link works before it goes live --
     * the same rule PageController applies to draft pages.
     */
    public function show(Request $request, Vehicle $vehicle): View
    {
        abort_unless($vehicle->is_published || $request->user()?->can('update', $vehicle), 404);

        $articles = Article::query()
            ->published()
            ->where(fn ($query) => $query
                ->where('vehicle_id', $vehicle->id)
                ->orWhereIn('topic', [ArticleTopic::Cars, ArticleTopic::Batteries, ArticleTopic::Charging]))
            ->latestFirst()
            ->limit(3)
            ->get();

        return view('cars.show', [
            'vehicle' => $vehicle,
            'articles' => $articles,
            'otherVehicles' => Vehicle::query()->published()->whereKeyNot($vehicle->id)->ordered()->limit(3)->get(),
        ]);
    }
}
