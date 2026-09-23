<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
use App\Settings\Settings;
use App\Shop\Checkout;
use App\Shop\Money;
use App\Shop\OrderStatus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Orders placed at checkout: read, fulfil, refund.
 *
 * There is no create or edit form -- an order is a record of a sale. Refunding
 * goes through App\Shop\Checkout so the licences return to stock under the
 * same lock that took them out.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getModelLabel(): string
    {
        return __('shop.orders.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('shop.orders.plural_label');
    }

    /**
     * The count of paid orders still waiting to be fulfilled.
     */
    public static function getNavigationBadge(): ?string
    {
        $waiting = Order::query()->where('status', OrderStatus::Paid)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('reference')
                    ->label(__('shop.orders.fields.reference'))
                    ->copyable(),
                TextEntry::make('status')
                    ->label(__('shop.orders.fields.status'))
                    ->formatStateUsing(fn (OrderStatus $state): string => __($state->label()))
                    ->badge()
                    ->color(fn (OrderStatus $state): string => $state->color()),
                TextEntry::make('customer_name')
                    ->label(__('shop.orders.fields.customer')),
                TextEntry::make('customer_email')
                    ->label(__('shop.orders.fields.email'))
                    ->copyable(),
                TextEntry::make('created_at')
                    ->label(__('shop.orders.fields.placed'))
                    ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state)),
                TextEntry::make('fulfilled_at')
                    ->label(__('shop.orders.fields.fulfilled'))
                    ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state))
                    ->placeholder('—'),
                RepeatableEntry::make('items')
                    ->label(__('shop.orders.fields.items'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('package_title')
                            ->hiddenLabel(),
                        TextEntry::make('price_cents')
                            ->hiddenLabel()
                            ->alignEnd()
                            ->formatStateUsing(fn (int $state): string => Money::format($state)),
                    ]),
                TextEntry::make('total_cents')
                    ->label(__('shop.orders.fields.total'))
                    ->formatStateUsing(fn (Order $record): string => $record->formattedTotal())
                    ->weight('bold'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label(__('shop.orders.fields.reference'))
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('customer_name')
                    ->label(__('shop.orders.fields.customer'))
                    ->description(fn (Order $record): string => $record->customer_email)
                    ->searchable(['customer_name', 'customer_email']),
                TextColumn::make('items_count')
                    ->label(__('shop.orders.fields.items'))
                    ->counts('items'),
                TextColumn::make('total_cents')
                    ->label(__('shop.orders.fields.total'))
                    ->formatStateUsing(fn (Order $record): string => $record->formattedTotal())
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('shop.orders.fields.status'))
                    ->formatStateUsing(fn (OrderStatus $state): string => __($state->label()))
                    ->badge()
                    ->color(fn (OrderStatus $state): string => $state->color()),
                TextColumn::make('created_at')
                    ->label(__('shop.orders.fields.placed'))
                    ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state))
                    ->sortable(),
            ])
            // Newest first: the order that just came in is the one to deal with.
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('shop.orders.fields.status'))
                    ->options(collect(OrderStatus::cases())
                        ->mapWithKeys(fn (OrderStatus $status): array => [$status->value => __($status->label())])
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('fulfil')
                    ->label(__('shop.orders.actions.fulfil'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::Paid)
                    ->authorize(fn (Order $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (Order $record): void {
                        app(Checkout::class)->fulfil($record);

                        Notification::make()->success()->title(__('shop.orders.notifications.fulfilled'))->send();
                    }),
                Action::make('refund')
                    ->label(__('shop.orders.actions.refund'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('shop.orders.actions.refund_confirm'))
                    ->visible(fn (Order $record): bool => $record->status !== OrderStatus::Refunded)
                    ->authorize(fn (Order $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (Order $record): void {
                        app(Checkout::class)->refund($record);

                        Notification::make()->success()->title(__('shop.orders.notifications.refunded'))->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrders::route('/'),
        ];
    }
}
