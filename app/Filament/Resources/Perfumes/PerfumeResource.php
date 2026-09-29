<?php

namespace App\Filament\Resources\Perfumes;

use App\Filament\Resources\Perfumes\Pages\ManagePerfumes;
use App\Models\Brand;
use App\Models\Perfume;
use App\Perfumes\Enums\Audience;
use App\Perfumes\Enums\Concentration;
use App\Perfumes\Enums\Family;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class PerfumeResource extends Resource
{
    protected static ?string $model = Perfume::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('perfumes.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('perfumes.perfumes.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('perfumes.perfumes.plural_label');
    }

    /**
     * @param  class-string<Family|Concentration|Audience>  $enum
     * @return array<string, string>
     */
    private static function enumOptions(string $enum): array
    {
        return collect($enum::cases())->mapWithKeys(fn (Family|Concentration|Audience $case): array => [$case->value => __($case->label())])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label(__('perfumes.perfumes.fields.name'))
                        ->required()
                        ->maxLength(255),
                    Select::make('brand_id')
                        ->label(__('perfumes.perfumes.fields.brand'))
                        ->relationship('brand', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            TextInput::make('name')->required()->maxLength(255),
                            TextInput::make('country')->maxLength(100),
                        ])
                        ->createOptionUsing(fn (array $data): int => Brand::create([...$data, 'slug' => Str::slug($data['name'])])->id),
                    TextInput::make('external_id')
                        ->label(__('perfumes.perfumes.fields.external_id'))
                        ->helperText(__('perfumes.perfumes.fields.external_id_help'))
                        ->default(fn (): string => (string) Str::uuid())
                        ->required()
                        ->unique(ignoreRecord: true),
                    TextInput::make('slug')
                        ->label(__('perfumes.perfumes.fields.slug'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']),
                    TextInput::make('perfumer')
                        ->label(__('perfumes.perfumes.fields.perfumer'))
                        ->maxLength(255),
                    TextInput::make('release_year')
                        ->label(__('perfumes.perfumes.fields.release_year'))
                        ->integer()
                        ->minValue(1700)
                        ->maxValue((int) date('Y') + 1),
                ]),
                Grid::make(3)->schema([
                    Select::make('family')
                        ->label(__('perfumes.perfumes.fields.family'))
                        ->options(self::enumOptions(Family::class)),
                    Select::make('concentration')
                        ->label(__('perfumes.perfumes.fields.concentration'))
                        ->options(self::enumOptions(Concentration::class)),
                    Select::make('gender')
                        ->label(__('perfumes.perfumes.fields.gender'))
                        ->options(self::enumOptions(Audience::class)),
                ]),
                TagsInput::make('top_notes')->label(__('perfumes.perfumes.fields.top_notes'))->columnSpanFull(),
                TagsInput::make('heart_notes')->label(__('perfumes.perfumes.fields.heart_notes'))->columnSpanFull(),
                TagsInput::make('base_notes')->label(__('perfumes.perfumes.fields.base_notes'))->columnSpanFull(),
                Textarea::make('description')
                    ->label(__('perfumes.perfumes.fields.description'))
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('brand'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('perfumes.perfumes.fields.name'))
                    ->description(fn (Perfume $record): string => $record->brand->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand.name')
                    ->label(__('perfumes.perfumes.fields.brand'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('family')
                    ->label(__('perfumes.perfumes.fields.family'))
                    ->formatStateUsing(fn (Family $state): string => __($state->label()))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('release_year')
                    ->label(__('perfumes.perfumes.fields.release_year'))
                    ->sortable(),
                TextColumn::make('followers_count')
                    ->label(__('perfumes.perfumes.fields.followers'))
                    ->counts('followers')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('perfumes.perfumes.fields.updated_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('followers_count', 'desc')
            ->filters([
                SelectFilter::make('family')
                    ->label(__('perfumes.perfumes.fields.family'))
                    ->options(self::enumOptions(Family::class)),
                SelectFilter::make('brand')
                    ->label(__('perfumes.perfumes.fields.brand'))
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordAction('edit')
            ->recordUrl(null)
            ->recordActions([
                Action::make('visit')
                    ->label(__('perfumes.perfumes.actions.view'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Perfume $record): string => route('perfumes.show', $record))
                    ->openUrlInNewTab(),
                EditAction::make()->modalWidth(Width::ThreeExtraLarge),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePerfumes::route('/'),
        ];
    }
}
