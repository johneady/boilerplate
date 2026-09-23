<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ManageRecords;

/**
 * The order list. No create action: orders come from checkout, never the panel.
 */
class ManageOrders extends ManageRecords
{
    protected static string $resource = OrderResource::class;
}
