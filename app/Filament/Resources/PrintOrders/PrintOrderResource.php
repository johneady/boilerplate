<?php

namespace App\Filament\Resources\PrintOrders;

use App\Filament\Resources\PrintOrders\Pages\ListPrintOrders;
use App\Livewire\Prints\FulfillmentConsole;
use App\Models\PrintOrder;
use App\Models\PrintOrderItem;
use App\Prints\Enums\PrintChannel;
use App\Prints\Enums\PrintOrderStatus;
use App\Prints\Enums\PrintPaymentStatus;
use App\Prints\PrintPricing;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The lab's record of every photo order in the admin panel.
 *
 * Deliberately a ledger rather than a workspace: viewing only, with no create,
 * edit or delete. Orders arrive from customers' phones and are worked in the
 * fulfillment console, where the status transitions are guarded -- this
 * resource exists so the whole history is searchable, long after the console
 * has moved on to the next family at the counter.
 */
class PrintOrderResource extends Resource
{
    protected static ?string $model = PrintOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static string|UnitEnum|null $navigationGroup = 'Photo lab';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getModelLabel(): string
    {
        return __('print-orders.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('print-orders.resource.plural_label');
    }

    /**
     * The photos and their counters are on every row and every view, so they
     * travel with the query rather than loading per order.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['items.media', 'location']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn (PrintOrder $record): string => $record->code)
                    ->schema([
                        TextEntry::make('channel')
                            ->label(__('print-orders.fields.channel'))
                            ->badge()
                            ->formatStateUsing(fn (PrintChannel $state): string => __($state->label()))
                            ->color(fn (PrintChannel $state): string => $state->color()),
                        TextEntry::make('status')
                            ->label(__('print-orders.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn (PrintOrderStatus $state): string => __($state->label()))
                            ->color(fn (PrintOrderStatus $state): string => $state->color()),
                        TextEntry::make('payment_status')
                            ->label(__('print-orders.fields.payment_status'))
                            ->badge()
                            ->formatStateUsing(fn (PrintPaymentStatus $state): string => __($state->label()))
                            ->color(fn (PrintPaymentStatus $state): string => $state->color()),
                        TextEntry::make('location.name')
                            ->label(__('print-orders.fields.location'))
                            ->placeholder('—'),
                        TextEntry::make('customer_name')
                            ->label(__('print-orders.fields.customer_name')),
                        TextEntry::make('customer_email')
                            ->label(__('print-orders.fields.customer_email'))
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('customer_phone')
                            ->label(__('print-orders.fields.customer_phone'))
                            ->placeholder('—'),
                        TextEntry::make('mailing_address')
                            ->label(__('print-orders.fields.mailing_address'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('placed')
                            ->label(__('print-orders.fields.placed'))
                            ->state(fn (PrintOrder $record): string => $record->created_at->toDayDateTimeString()),
                    ])
                    ->columns(3),
                Section::make(__('print-orders.fields.photos'))
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                ImageEntry::make('photo')
                                    ->hiddenLabel()
                                    ->height(120)
                                    ->checkFileExistence(false)
                                    ->state(fn (PrintOrderItem $record): ?string => $record->media?->url('thumb') ?? $record->media?->url()),
                                TextEntry::make('quantity')
                                    ->label(__('print-orders.fields.quantity'))
                                    ->badge()
                                    ->formatStateUsing(fn (int $state): string => "×{$state}"),
                                TextEntry::make('printer')
                                    ->label(__('print-orders.fields.printer'))
                                    ->placeholder('—')
                                    ->formatStateUsing(fn (?string $state): string => FulfillmentConsole::PRINTERS[$state] ?? (string) $state),
                            ])
                            ->columns([
                                'default' => 4,
                                'sm' => 6,
                                'lg' => 8,
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make(__('print-orders.fields.total'))
                    ->schema([
                        TextEntry::make('prints')
                            ->label(__('print-orders.fields.prints'))
                            ->state(fn (PrintOrder $record): string => (string) $record->printCount()),
                        TextEntry::make('total')
                            ->label(__('print-orders.fields.total'))
                            ->state(fn (PrintOrder $record): string => PrintPricing::money($record->prints_total_cents)),
                        TextEntry::make('savings')
                            ->label(__('print-orders.fields.savings'))
                            ->state(fn (PrintOrder $record): string => PrintPricing::money($record->savings_cents)),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('print-orders.resource.plural_label'))
            ->emptyStateDescription(__('print-orders.table.empty'))
            ->columns([
                TextColumn::make('code')
                    ->label(__('print-orders.fields.code'))
                    ->badge()
                    ->color('gray')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
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
                TextColumn::make('customer_name')
                    ->label(__('print-orders.fields.customer_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.name')
                    ->label(__('print-orders.fields.location'))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->label(__('print-orders.fields.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn (PrintPaymentStatus $state): string => __($state->label()))
                    ->color(fn (PrintPaymentStatus $state): string => $state->color()),
                TextColumn::make('prints')
                    ->label(__('print-orders.fields.prints'))
                    ->state(fn (PrintOrder $record): string => (string) $record->printCount())
                    ->alignEnd(),
                TextColumn::make('prints_total_cents')
                    ->label(__('print-orders.fields.total'))
                    ->formatStateUsing(fn (int $state): string => PrintPricing::money($state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('print-orders.fields.placed'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('print-orders.fields.status'))
                    ->options(collect(PrintOrderStatus::cases())
                        ->mapWithKeys(fn (PrintOrderStatus $status): array => [
                            $status->value => __($status->label()),
                        ])
                        ->all()),
                SelectFilter::make('channel')
                    ->label(__('print-orders.fields.channel'))
                    ->options(collect(PrintChannel::cases())
                        ->mapWithKeys(fn (PrintChannel $channel): array => [
                            $channel->value => __($channel->label()),
                        ])
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrintOrders::route('/'),
        ];
    }
}
