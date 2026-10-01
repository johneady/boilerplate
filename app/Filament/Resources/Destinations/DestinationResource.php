<?php

namespace App\Filament\Resources\Destinations;

use App\Filament\Resources\Destinations\Pages\ManageDestinations;
use App\Models\Destination;
use App\Travel\Region;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * The places the agency sells, each with its own public landing page.
 *
 * Photos are not uploadable from here in the demo: user images go through the
 * re-encoding media pipeline (.ai/rules/concerns.md), which is not wired to
 * travel content yet.
 */
class DestinationResource extends Resource
{
    protected static ?string $model = Destination::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeEuropeAfrica;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('travel.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('travel.destinations.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('travel.destinations.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('travel.destinations.fields.name'))
                    ->required()
                    ->maxLength(120)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, string $operation, Set $set): void {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label(__('travel.destinations.fields.slug'))
                    ->helperText(__('travel.destinations.fields.slug_help'))
                    ->required()
                    ->maxLength(120)
                    ->unique(ignoreRecord: true)
                    ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']),
                TextInput::make('country')
                    ->label(__('travel.destinations.fields.country'))
                    ->required()
                    ->maxLength(120),
                Select::make('region')
                    ->label(__('travel.destinations.fields.region'))
                    ->options(Region::options())
                    ->required(),
                TextInput::make('tagline')
                    ->label(__('travel.destinations.fields.tagline'))
                    ->required()
                    ->maxLength(160)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label(__('travel.destinations.fields.description'))
                    ->helperText(__('travel.destinations.fields.description_help'))
                    ->required()
                    ->rows(6)
                    ->columnSpanFull(),
                TextInput::make('best_time')
                    ->label(__('travel.destinations.fields.best_time'))
                    ->maxLength(200)
                    ->columnSpanFull(),
                Toggle::make('is_featured')
                    ->label(__('travel.destinations.fields.is_featured')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label(__('travel.destinations.fields.photo'))
                    ->state(fn (Destination $record): ?string => $record->imageUrl())
                    ->imageHeight(48)
                    ->imageWidth(72),
                TextColumn::make('name')
                    ->label(__('travel.destinations.fields.name'))
                    ->description(fn (Destination $record): string => $record->country)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('region')
                    ->label(__('travel.destinations.fields.region'))
                    ->formatStateUsing(fn (Region $state): string => __($state->label()))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('tours_count')
                    ->label(__('travel.destinations.fields.tours'))
                    ->counts('tours')
                    ->sortable(),
                IconColumn::make('is_featured')
                    ->label(__('travel.destinations.fields.is_featured'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                Action::make('visit')
                    ->label(__('travel.destinations.actions.view'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Destination $record): string => route('destinations.show', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Destination $record): bool => $record->tours()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDestinations::route('/'),
        ];
    }
}
