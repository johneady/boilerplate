<?php

namespace App\Filament\Resources\PrintLocations;

use App\Filament\Resources\PrintLocations\Pages\ManagePrintLocations;
use App\Models\PrintLocation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * The counters and their QR codes.
 *
 * A counter is one printable thing: a QR code that opens the in-store photo
 * flow branded to the lab, with no payment step. The manager's job here is
 * deciding which counters exist -- the signage (the QR, and the URL it
 * encodes) renders itself from the row.
 */
class PrintLocationResource extends Resource
{
    protected static ?string $model = PrintLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'Photo lab';

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('print-locations.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('print-locations.resource.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('print-locations.fields.name'))
                    ->required()
                    ->maxLength(255)
                    ->live()
                    // Fills the slug from the name while creating, so the
                    // QR's URL is readable on a printed sign (and printable
                    // as text beneath the code, for anyone whose camera will
                    // not scan).
                    ->afterStateUpdated(function (Set $set, ?string $state, ?PrintLocation $record): void {
                        if ($record === null && filled($state)) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label(__('print-locations.fields.slug'))
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText(__('print-locations.fields.slug_helper')),
                TextInput::make('address')
                    ->label(__('print-locations.fields.address'))
                    ->required()
                    ->maxLength(255)
                    ->helperText(__('print-locations.fields.address_helper')),
                Toggle::make('is_active')
                    ->label(__('print-locations.fields.is_active'))
                    ->default(true)
                    ->helperText(__('print-locations.fields.is_active_helper')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('print-locations.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('print-locations.fields.slug'))
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('address')
                    ->label(__('print-locations.fields.address'))
                    ->limit(40),
                TextColumn::make('orders_count')
                    ->label(__('print-locations.fields.orders'))
                    ->counts('orders')
                    ->alignEnd(),
                IconColumn::make('is_active')
                    ->label(__('print-locations.fields.is_active'))
                    ->boolean(),
            ])
            ->recordActions([
                Action::make('qr')
                    ->label(__('print-locations.qr.show'))
                    ->icon(Heroicon::OutlinedQrCode)
                    ->modalHeading(__('print-locations.qr.heading'))
                    ->modalDescription(fn (PrintLocation $record): string => __(
                        'print-locations.qr.description',
                        ['name' => $record->name],
                    ))
                    ->modalContent(fn (PrintLocation $record): HtmlString => new HtmlString(
                        view('filament.resources.print-locations.qr', ['location' => $record])->render(),
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close')),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePrintLocations::route('/'),
        ];
    }
}
