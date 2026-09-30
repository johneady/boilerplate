<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Ordering\Fulfilment;
use App\Ordering\OrderStatus;
use App\Ordering\Price;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Orders placed on the website -- the kitchen's order book.
 *
 * Worked from the table: the button on each row moves an order to its next
 * step, and the tabs split what is in progress from what is finished. There
 * is no create or edit form; an order is what the customer asked for.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('ordering.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('ordering.orders.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ordering.orders.plural_label');
    }

    /**
     * How many new orders nobody has started, or no badge at all.
     */
    public static function getNavigationBadge(): ?string
    {
        $new = Order::query()->where('status', OrderStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('ordering.orders.open_badge');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label(__('ordering.orders.fields.number'))
                    ->weight('bold')
                    ->description(fn (Order $record): string => app(Settings::class)->formatRelative($record->created_at))
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label(__('ordering.orders.fields.customer'))
                    ->description(fn (Order $record): string => $record->customer_phone)
                    ->searchable(),
                TextColumn::make('items_summary')
                    ->label(__('ordering.orders.fields.items'))
                    ->state(fn (Order $record): string => $record->items
                        ->map(fn (OrderItem $item): string => $item->quantity.'× '.$item->product_name)
                        ->implode(', '))
                    ->wrap()
                    ->limit(60),
                TextColumn::make('fulfilment')
                    ->label(__('ordering.orders.fields.fulfilment'))
                    ->badge()
                    ->color('gray')
                    ->icon(fn (Fulfilment $state): string => 'heroicon-m-'.$state->icon())
                    ->formatStateUsing(fn (Fulfilment $state): string => __($state->label())),
                TextColumn::make('ready_at')
                    ->label(__('ordering.orders.fields.ready_at'))
                    ->formatStateUsing(fn (Order $record): string => $record->readyLabel())
                    ->sortable(),
                TextColumn::make('total_cents')
                    ->label(__('ordering.orders.fields.total'))
                    ->formatStateUsing(fn (int $state): string => Price::format($state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('ordering.orders.fields.status'))
                    ->badge()
                    ->color(fn (OrderStatus $state): string => $state->color())
                    ->formatStateUsing(fn (OrderStatus $state): string => __($state->label())),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('items'))
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->filters([
                SelectFilter::make('fulfilment')
                    ->label(__('ordering.orders.fields.fulfilment'))
                    ->options(collect(Fulfilment::cases())->mapWithKeys(fn (Fulfilment $case): array => [$case->value => __($case->label())])->all()),
            ])
            ->recordActions([
                OrderActions::advance(),
                ViewAction::make()->iconButton(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('ordering.orders.sections.items'))
                    ->columnSpan(2)
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make(__('ordering.orders.fields.quantity')),
                                TableColumn::make(__('ordering.orders.fields.product')),
                                TableColumn::make(__('ordering.orders.fields.unit_price')),
                                TableColumn::make(__('ordering.orders.fields.line_total')),
                            ])
                            ->schema([
                                TextEntry::make('quantity'),
                                TextEntry::make('product_name')->weight('bold'),
                                TextEntry::make('unit_price_cents')->formatStateUsing(fn (int $state): string => Price::format($state)),
                                TextEntry::make('line_total_cents')->formatStateUsing(fn (int $state): string => Price::format($state)),
                            ]),
                        TextEntry::make('subtotal_cents')
                            ->label(__('ordering.orders.fields.subtotal'))
                            ->inlineLabel()
                            ->formatStateUsing(fn (int $state): string => Price::format($state)),
                        TextEntry::make('delivery_fee_cents')
                            ->label(__('ordering.orders.fields.delivery_fee'))
                            ->inlineLabel()
                            ->visible(fn (Order $record): bool => $record->fulfilment === Fulfilment::Delivery)
                            ->formatStateUsing(fn (int $state): string => $state === 0 ? __('Free') : Price::format($state)),
                        TextEntry::make('total_cents')
                            ->label(__('ordering.orders.fields.total'))
                            ->inlineLabel()
                            ->weight('bold')
                            ->size('lg')
                            ->formatStateUsing(fn (int $state): string => Price::format($state)),
                        TextEntry::make('notes')
                            ->label(__('ordering.orders.fields.notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('ordering.orders.sections.customer'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('ordering.orders.fields.status'))
                            ->badge()
                            ->color(fn (OrderStatus $state): string => $state->color())
                            ->formatStateUsing(fn (OrderStatus $state): string => __($state->label())),
                        TextEntry::make('ready_at')
                            ->label(__('ordering.orders.fields.ready_at'))
                            ->formatStateUsing(fn (Order $record): string => $record->readyLabel()),
                        TextEntry::make('fulfilment')
                            ->label(__('ordering.orders.fields.fulfilment'))
                            ->formatStateUsing(fn (Fulfilment $state): string => __($state->label())),
                        TextEntry::make('delivery_address')
                            ->label(__('ordering.orders.fields.address'))
                            ->visible(fn (Order $record): bool => $record->fulfilment === Fulfilment::Delivery),
                        TextEntry::make('customer_name')
                            ->label(__('ordering.orders.fields.customer')),
                        TextEntry::make('customer_phone')
                            ->label(__('ordering.orders.fields.phone'))
                            ->url(fn (Order $record): string => 'tel:'.preg_replace('/[^0-9+]/', '', $record->customer_phone)),
                        TextEntry::make('customer_email')
                            ->label(__('ordering.orders.fields.email'))
                            ->url(fn (Order $record): string => 'mailto:'.$record->customer_email),
                        TextEntry::make('created_at')
                            ->label(__('ordering.orders.fields.placed_at'))
                            ->formatStateUsing(fn (Order $record): string => app(Settings::class)->formatDateTime($record->created_at)),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
