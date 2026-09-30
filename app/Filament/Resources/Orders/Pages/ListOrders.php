<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Ordering\OrderStatus;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * The order book, split the way the counter works it: what is in progress
 * (soonest first), just the new ones, what is ready, and what is finished.
 */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $open = [OrderStatus::New, OrderStatus::Preparing, OrderStatus::Ready];

        return [
            'active' => Tab::make(__('ordering.orders.tabs.active'))
                ->badge(fn (): int => Order::query()->whereIn('status', $open)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', $open)->reorder('ready_at')),
            'new' => Tab::make(__('ordering.orders.tabs.new'))
                ->badge(fn (): int => Order::query()->where('status', OrderStatus::New)->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', OrderStatus::New)->reorder('ready_at')),
            'ready' => Tab::make(__('ordering.orders.tabs.ready'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', OrderStatus::Ready)->reorder('ready_at')),
            'finished' => Tab::make(__('ordering.orders.tabs.finished'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])),
            'all' => Tab::make(__('ordering.orders.tabs.all')),
        ];
    }
}
