<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Vehicle;
use Illuminate\View\View;

/**
 * The Voltiva home page: hero, the car range, the "why Voltiva" sections,
 * the latest advice and the enquiry form.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('welcome', [
            'vehicles' => Vehicle::query()->published()->ordered()->get(),
            'articles' => Article::query()->published()->latestFirst()->limit(3)->get(),
        ]);
    }
}
