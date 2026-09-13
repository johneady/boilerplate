<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * The latest orders placed on the site, as an activity list. Sample data
 * while the dashboard serves as a work sample.
 */
class RecentOrders extends Widget
{
    protected int|string|array $columnSpan = 2;

    protected string $view = 'filament.widgets.recent-orders';

    /**
     * @return array<int, array{customer: string, initials: string, product: string, placed_at: string, amount: string, status: string}>
     */
    public function getOrders(): array
    {
        return [
            ['customer' => 'Amelia Hartley', 'initials' => 'AH', 'product' => 'Website care plan (annual)', 'placed_at' => '12 minutes ago', 'amount' => '$1,140.00', 'status' => 'Paid'],
            ['customer' => 'Priya Nair', 'initials' => 'PN', 'product' => 'Bespoke quote builder', 'placed_at' => '1 hour ago', 'amount' => '$3,480.00', 'status' => 'Paid'],
            ['customer' => 'Glenrothes Brewery', 'initials' => 'GB', 'product' => 'Menu & bookings update', 'placed_at' => '3 hours ago', 'amount' => '$620.00', 'status' => 'Pending'],
            ['customer' => 'Marcus Oyelaran', 'initials' => 'MO', 'product' => 'Storefront theme', 'placed_at' => 'Yesterday', 'amount' => '$285.00', 'status' => 'Shipped'],
            ['customer' => 'Sandra Beckett', 'initials' => 'SB', 'product' => 'Booking calendar add-on', 'placed_at' => 'Yesterday', 'amount' => '$410.00', 'status' => 'Paid'],
            ['customer' => 'Firth Logistics', 'initials' => 'FL', 'product' => 'Driver portal (phase 1)', 'placed_at' => '2 days ago', 'amount' => '$5,900.00', 'status' => 'Paid'],
            ['customer' => 'Tom Wheldon', 'initials' => 'TW', 'product' => 'Website care plan (monthly)', 'placed_at' => '3 days ago', 'amount' => '$99.00', 'status' => 'Refunded'],
        ];
    }
}
