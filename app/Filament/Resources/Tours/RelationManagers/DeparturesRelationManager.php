<?php

namespace App\Filament\Resources\Tours\RelationManagers;

use App\Filament\Forms\MoneyInput;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Payments\Enums\Currency;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A tour's scheduled departures. "Seats taken" is editable for bookings made
 * by phone; confirmed online requests add to it themselves.
 */
class DeparturesRelationManager extends RelationManager
{
    protected static string $relationship = 'departures';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('travel.departures.title');
    }

    public function form(Schema $schema): Schema
    {
        /** @var Tour $tour */
        $tour = $this->getOwnerRecord();

        return $schema
            ->components([
                DatePicker::make('starts_on')
                    ->label(__('travel.departures.fields.starts_on'))
                    ->native(false)
                    ->required(),
                MoneyInput::make('price_per_person_cents', Currency::USD)
                    ->label(__('travel.departures.fields.price'))
                    ->helperText(__('travel.departures.fields.price_help'))
                    ->positive(),
                TextInput::make('seats_total')
                    ->label(__('travel.departures.fields.seats_total'))
                    ->integer()
                    ->minValue(1)
                    ->default($tour->group_size_max)
                    ->required(),
                TextInput::make('seats_held')
                    ->label(__('travel.departures.fields.seats_held'))
                    ->helperText(__('travel.departures.fields.seats_held_help'))
                    ->integer()
                    ->minValue(0)
                    ->lte('seats_total')
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('starts_on')
                    ->label(__('travel.departures.fields.starts_on'))
                    ->state(fn (TourDeparture $record): string => $record->dateRange())
                    ->sortable(['starts_on']),
                TextColumn::make('price_per_person_cents')
                    ->label(__('travel.departures.fields.price'))
                    ->state(fn (TourDeparture $record): string => $record->formattedPrice()),
                TextColumn::make('seats_held')
                    ->label(__('travel.departures.fields.seats_held'))
                    ->state(fn (TourDeparture $record): string => $record->seats_held.' / '.$record->seats_total),
                TextColumn::make('seats_left')
                    ->label(__('travel.departures.fields.seats_left'))
                    ->state(fn (TourDeparture $record): int => $record->seatsLeft())
                    ->badge()
                    ->color(fn (TourDeparture $record): string => match (true) {
                        $record->isSoldOut() => 'gray',
                        $record->isNearlyFull() => 'warning',
                        default => 'success',
                    }),
            ])
            ->defaultSort('starts_on')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
