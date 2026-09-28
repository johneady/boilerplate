<?php

namespace App\Filament\Resources\PrintOrders\Pages;

use App\Auth\Permission;
use App\Filament\Resources\PrintOrders\PrintOrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListPrintOrders extends ListRecords
{
    protected static string $resource = PrintOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fulfillmentConsole')
                ->label(__('Open the fulfillment console'))
                ->icon('heroicon-o-computer-desktop')
                ->color('primary')
                ->url(route('prints.fulfill'))
                ->visible(fn (): bool => auth()->user()?->hasPermission(Permission::FulfillPrintOrders) ?? false),
        ];
    }
}
