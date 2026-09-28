<?php

namespace App\Filament\Widgets;

use App\Auth\Permission;
use App\Models\PrintOrder;
use App\Prints\Enums\PrintChannel;
use App\Prints\Enums\PrintOrderStatus;
use App\Prints\PrintPricing;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The queue itself, on the dashboard: orders still owed work, oldest first.
 *
 * The working view lives in the fulfillment console (that is the tablet's
 * job); this is the manager's eye on the same queue without leaving the
 * panel.
 */
class PrintQueue extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * Not polled: the console polls, and the dashboard re-renders whenever a
     * staff member returns to it.
     */
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission(Permission::ViewPrintOrders) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('print-orders.queue.heading'))
            ->description(__('print-orders.queue.description'))
            ->query(fn (): Builder => PrintOrder::query()
                ->open()
                ->with(['location'])
                ->orderBy('created_at'))
            ->columns([
                TextColumn::make('code')
                    ->label(__('print-orders.fields.code'))
                    ->badge()
                    ->color('gray')
                    ->fontFamily('mono'),
                TextColumn::make('customer_name')
                    ->label(__('print-orders.fields.customer_name')),
                TextColumn::make('channel')
                    ->label(__('print-orders.fields.channel'))
                    ->badge()
                    ->formatStateUsing(fn (PrintChannel $state): string => __($state->label()))
                    ->color(fn (PrintChannel $state): string => $state->color()),
                TextColumn::make('status')
                    ->label(__('print-orders.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (PrintOrderStatus $state): string => __($state->label()))
                    ->color(fn (PrintOrderStatus $state): string => $state->color()),
                TextColumn::make('prints')
                    ->label(__('print-orders.fields.prints'))
                    ->state(fn (PrintOrder $record): string => (string) $record->printCount())
                    ->alignEnd(),
                TextColumn::make('prints_total_cents')
                    ->label(__('print-orders.fields.total'))
                    ->formatStateUsing(fn (int $state): string => PrintPricing::money($state))
                    ->alignEnd(),
                TextColumn::make('created_at')
                    ->label(__('print-orders.fields.placed'))
                    ->since(),
            ])
            ->recordUrl(fn (PrintOrder $record): string => route('filament.admin.resources.print-orders.view', ['record' => $record]));
    }
}
