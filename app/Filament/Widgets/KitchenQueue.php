<?php

namespace App\Filament\Widgets;

use App\Auth\Permission;
use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Ordering\Fulfilment;
use App\Ordering\OrderStatus;
use App\Ordering\Price;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Every open order, soonest first, with the button that moves it along --
 * so the counter can run from the dashboard without opening the order list.
 */
class KitchenQueue extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission(Permission::ViewOrders) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('ordering.widgets.queue_heading'))
            ->description(__('ordering.widgets.queue_description'))
            ->query(fn () => Order::query()
                ->with('items')
                ->whereIn('status', [OrderStatus::New, OrderStatus::Preparing, OrderStatus::Ready])
                ->orderBy('ready_at'))
            ->poll('30s')
            ->paginated(false)
            ->emptyStateHeading(__('ordering.widgets.queue_empty'))
            ->emptyStateIcon('heroicon-o-face-smile')
            ->columns([
                TextColumn::make('ready_at')
                    ->label(__('ordering.orders.fields.ready_at'))
                    ->formatStateUsing(fn (Order $record): string => $record->readyLabel())
                    ->weight('bold'),
                TextColumn::make('number')
                    ->label(__('ordering.orders.fields.number'))
                    ->description(fn (Order $record): string => $record->customer_name),
                TextColumn::make('items_summary')
                    ->label(__('ordering.orders.fields.items'))
                    ->state(fn (Order $record): string => $record->items
                        ->map(fn (OrderItem $item): string => $item->quantity.'× '.$item->product_name)
                        ->implode(', '))
                    ->wrap(),
                TextColumn::make('fulfilment')
                    ->label(__('ordering.orders.fields.fulfilment'))
                    ->badge()
                    ->color('gray')
                    ->icon(fn (Fulfilment $state): string => 'heroicon-m-'.$state->icon())
                    ->formatStateUsing(fn (Fulfilment $state): string => __($state->label())),
                TextColumn::make('total_cents')
                    ->label(__('ordering.orders.fields.total'))
                    ->formatStateUsing(fn (int $state): string => Price::format($state)),
                TextColumn::make('status')
                    ->label(__('ordering.orders.fields.status'))
                    ->badge()
                    ->color(fn (OrderStatus $state): string => $state->color())
                    ->formatStateUsing(fn (OrderStatus $state): string => __($state->label())),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                OrderActions::advance(),
            ]);
    }
}
