<?php

namespace App\Filament\Resources\Tours;

use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\Tours\Pages\CreateTour;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\Pages\ListTours;
use App\Filament\Resources\Tours\RelationManagers\DeparturesRelationManager;
use App\Models\Tour;
use App\Payments\Enums\Currency;
use App\Travel\TourStyle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Tour packages: everything on a tour's public page, with its departure dates
 * managed beneath the form.
 */
class TourResource extends Resource
{
    protected static ?string $model = Tour::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('travel.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('travel.tours.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('travel.tours.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('travel.tours.sections.basics'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('travel.tours.fields.name'))
                            ->required()
                            ->maxLength(160)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, string $operation, Set $set): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label(__('travel.tours.fields.slug'))
                            ->required()
                            ->maxLength(160)
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']),
                        Select::make('destination_id')
                            ->label(__('travel.tours.fields.destination'))
                            ->relationship('destination', 'name')
                            ->required()
                            ->preload(),
                        Select::make('style')
                            ->label(__('travel.tours.fields.style'))
                            ->options(TourStyle::options())
                            ->required(),
                        TextInput::make('summary')
                            ->label(__('travel.tours.fields.summary'))
                            ->helperText(__('travel.tours.fields.summary_help'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Toggle::make('is_published')
                            ->label(__('travel.tours.fields.is_published'))
                            ->default(true),
                        Toggle::make('is_featured')
                            ->label(__('travel.tours.fields.is_featured')),
                    ]),
                Section::make(__('travel.tours.sections.pricing'))
                    ->columns(4)
                    ->schema([
                        MoneyInput::make('price_per_person_cents', Currency::USD)
                            ->label(__('travel.tours.fields.price'))
                            ->helperText(__('travel.tours.fields.price_help'))
                            ->required()
                            ->positive(),
                        MoneyInput::make('single_supplement_cents', Currency::USD)
                            ->label(__('travel.tours.fields.single_supplement'))
                            ->helperText(__('travel.tours.fields.single_supplement_help'))
                            ->default(0)
                            ->required(),
                        TextInput::make('duration_days')
                            ->label(__('travel.tours.fields.duration_days'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required(),
                        TextInput::make('group_size_max')
                            ->label(__('travel.tours.fields.group_size_max'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required(),
                    ]),
                Section::make(__('travel.tours.sections.content'))
                    ->schema([
                        Textarea::make('description')
                            ->label(__('travel.tours.fields.description'))
                            ->helperText(__('travel.tours.fields.description_help'))
                            ->required()
                            ->rows(6),
                        TagsInput::make('highlights')
                            ->label(__('travel.tours.fields.highlights')),
                        TagsInput::make('inclusions')
                            ->label(__('travel.tours.fields.inclusions')),
                        Repeater::make('itinerary')
                            ->label(__('travel.tours.fields.itinerary'))
                            ->schema([
                                TextInput::make('day')
                                    ->label(__('travel.tours.fields.day'))
                                    ->required()
                                    ->maxLength(10),
                                TextInput::make('title')
                                    ->label(__('travel.tours.fields.day_title'))
                                    ->required()
                                    ->maxLength(120)
                                    ->columnSpan(3),
                                Textarea::make('body')
                                    ->label(__('travel.tours.fields.day_body'))
                                    ->required()
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => filled($state['title'] ?? null) ? ($state['day'] ?? '').' · '.$state['title'] : null),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['destination', 'bookableDepartures']))
            ->columns([
                ImageColumn::make('image_path')
                    ->label(__('travel.tours.fields.photo'))
                    // An absolute URL: ImageColumn passes a full URL through untouched
                    // but treats anything else as a path on its disk, which would
                    // turn the model's root-relative /storage/... into a broken link.
                    ->state(fn (Tour $record): ?string => $record->imageUrl() !== null ? url($record->imageUrl()) : null)
                    ->imageHeight(48)
                    ->imageWidth(72),
                TextColumn::make('name')
                    ->label(__('travel.tours.fields.name'))
                    ->description(fn (Tour $record): string => $record->destination->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('style')
                    ->label(__('travel.tours.fields.style'))
                    ->formatStateUsing(fn (TourStyle $state): string => __($state->label()))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('duration_days')
                    ->label(__('travel.tours.fields.duration_days'))
                    ->sortable(),
                TextColumn::make('price_per_person_cents')
                    ->label(__('travel.tours.fields.price'))
                    ->formatStateUsing(fn (Tour $record): string => $record->formattedPrice())
                    ->sortable(),
                TextColumn::make('next_departure')
                    ->label(__('travel.tours.fields.next_departure'))
                    ->state(fn (Tour $record): ?string => $record->bookableDepartures->first()?->starts_on->format('j M Y'))
                    ->placeholder('—'),
                ToggleColumn::make('is_published')
                    ->label(__('travel.tours.fields.is_published')),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('destination')
                    ->label(__('travel.tours.fields.destination'))
                    ->relationship('destination', 'name'),
                SelectFilter::make('style')
                    ->label(__('travel.tours.fields.style'))
                    ->options(TourStyle::options()),
            ])
            ->recordActions([
                Action::make('visit')
                    ->label(__('travel.tours.actions.view'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Tour $record): string => route('tours.show', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DeparturesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTours::route('/'),
            'create' => CreateTour::route('/create'),
            'edit' => EditTour::route('/{record}/edit'),
        ];
    }
}
