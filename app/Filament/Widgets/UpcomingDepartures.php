<?php

namespace App\Filament\Widgets;

use App\Auth\Permission;
use App\Filament\Resources\Tours\TourResource;
use App\Models\TourDeparture;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The next departures and how full they are, so operations can see which
 * dates need a push and which are about to sell out.
 */
class UpcomingDepartures extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission(Permission::ViewTripInquiries) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('travel.dashboard.upcoming'))
            ->query(fn (): Builder => TourDeparture::query()
                ->with('tour.destination')
                ->where('starts_on', '>', now()->toDateString())
                ->orderBy('starts_on'))
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('starts_on')
                    ->label(__('travel.departures.fields.starts_on'))
                    ->state(fn (TourDeparture $record): string => $record->dateRange()),
                TextColumn::make('tour.name')
                    ->label(__('travel.tours.label'))
                    ->description(fn (TourDeparture $record): string => $record->tour->destination->name),
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
            ->recordUrl(fn (TourDeparture $record): ?string => auth()->user()?->can('update', $record->tour)
                ? TourResource::getUrl('edit', ['record' => $record->tour])
                : null);
    }
}
