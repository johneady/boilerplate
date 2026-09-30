<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Order $order */
        $order = $this->getRecord();

        return __('Order :number', ['number' => $order->number]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('track')
                ->label(__('ordering.orders.actions.track'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Order $record): string => $record->trackingUrl())
                ->openUrlInNewTab(),
            OrderActions::cancel(),
            OrderActions::advance()->size('md'),
        ];
    }
}
