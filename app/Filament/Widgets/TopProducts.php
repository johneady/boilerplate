<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Best sellers over the last quarter, with each product's share of sales.
 * Sample data while the dashboard serves as a work sample.
 */
class TopProducts extends Widget
{
    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.top-products';

    /**
     * @return array<int, array{name: string, units: string, revenue: string, share: int, bar: string}>
     */
    public function getProducts(): array
    {
        return [
            ['name' => 'Website care plan (annual)', 'units' => '146 sold', 'revenue' => '$16,640', 'share' => 100, 'bar' => 'bg-blue-600'],
            ['name' => 'Bespoke quote builder', 'units' => '89 sold', 'revenue' => '$9,780', 'share' => 59, 'bar' => 'bg-emerald-500'],
            ['name' => 'Booking calendar add-on', 'units' => '71 sold', 'revenue' => '$6,910', 'share' => 42, 'bar' => 'bg-amber-500'],
            ['name' => 'Storefront theme', 'units' => '58 sold', 'revenue' => '$4,880', 'share' => 29, 'bar' => 'bg-violet-500'],
            ['name' => 'Menu & bookings update', 'units' => '37 sold', 'revenue' => '$2,590', 'share' => 16, 'bar' => 'bg-zinc-400'],
        ];
    }
}
