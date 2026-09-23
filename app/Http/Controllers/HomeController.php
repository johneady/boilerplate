<?php

namespace App\Http\Controllers;

use App\Bakery\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Contracts\View\View;

/**
 * The home page, which is the menu.
 *
 * Every available item, grouped by category in the enum's declaration order,
 * so the owner reorders the menu by dragging rows in the admin panel and never
 * has to touch the page itself.
 */
class HomeController extends Controller
{
    /**
     * Render the home page.
     */
    public function __invoke(): View
    {
        $items = MenuItem::query()->available()->ordered()->get();

        $menu = collect(MenuCategory::cases())
            ->map(fn (MenuCategory $category): array => [
                'category' => $category,
                'items' => $items->filter(fn (MenuItem $item): bool => $item->category === $category)->values(),
            ])
            ->filter(fn (array $section): bool => $section['items']->isNotEmpty())
            ->values();

        return view('welcome', [
            'menu' => $menu,
            'featured' => $items->where('is_featured', true)->take(3)->values(),
            'credits' => $items->pluck('image_credit', 'name')->filter(),
        ]);
    }
}
