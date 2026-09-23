<?php

namespace App\Filament\Resources\MenuItems;

use App\Bakery\DietaryTag;
use App\Bakery\MenuCategory;
use App\Bakery\Money;
use App\Filament\Resources\MenuItems\Pages\ManageMenuItems;
use App\Models\MenuItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * The bakery's menu, which is the public home page and the order form's list.
 *
 * "On the menu" is switched inline because it is what changes week to week
 * (sold out, out of season); rows are dragged into menu order; everything else
 * lives in the edit modal.
 */
class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static string|UnitEnum|null $navigationGroup = 'Bakery';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('bakery.menu.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('bakery.menu.plural_label');
    }

    /**
     * The number of items switched off, so a menu gap is noticed from anywhere
     * in the panel.
     */
    public static function getNavigationBadge(): ?string
    {
        $off = MenuItem::query()->where('is_available', false)->count();

        return $off > 0 ? (string) $off : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('bakery.menu.unavailable_badge');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('bakery.menu.sections.item'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('bakery.menu.fields.name'))
                            ->required()
                            ->maxLength(255)
                            // Fills the slug while creating only: renaming a
                            // live item must not break links already shared.
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, string $operation, Set $set): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label(__('bakery.menu.fields.slug'))
                            ->helperText(__('bakery.menu.fields.slug_help'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']),
                        Select::make('category')
                            ->label(__('bakery.menu.fields.category'))
                            ->options(static::categoryOptions())
                            ->required(),
                        CheckboxList::make('dietary')
                            ->label(__('bakery.menu.fields.dietary'))
                            ->options(static::dietaryOptions())
                            ->columns(2),
                        Textarea::make('description')
                            ->label(__('bakery.menu.fields.description'))
                            ->helperText(__('bakery.menu.fields.description_help'))
                            ->required()
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('bakery.menu.sections.ordering'))
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('price_cents')
                            ->label(__('bakery.menu.fields.price'))
                            ->prefix(Money::currency())
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required()
                            // Stored as integer cents, edited as a decimal.
                            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state / 100, 2, '.', ''))
                            ->dehydrateStateUsing(fn (string|int|float|null $state): int => (int) round(((float) $state) * 100)),
                        TextInput::make('price_unit')
                            ->label(__('bakery.menu.fields.price_unit'))
                            ->helperText(__('bakery.menu.fields.price_unit_help'))
                            ->required()
                            ->maxLength(64),
                        TextInput::make('serves')
                            ->label(__('bakery.menu.fields.serves'))
                            ->helperText(__('bakery.menu.fields.serves_help'))
                            ->maxLength(64),
                        TextInput::make('notice_days')
                            ->label(__('bakery.menu.fields.notice_days'))
                            ->helperText(__('bakery.menu.fields.notice_days_help'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(60)
                            ->suffix(__('bakery.menu.fields.days'))
                            ->default(2)
                            ->required(),
                        Toggle::make('is_available')
                            ->label(__('bakery.menu.fields.is_available'))
                            ->helperText(__('bakery.menu.fields.is_available_help'))
                            ->default(true)
                            ->columnSpan(2),
                        Toggle::make('is_seasonal')
                            ->label(__('bakery.menu.fields.is_seasonal'))
                            ->helperText(__('bakery.menu.fields.is_seasonal_help')),
                        Toggle::make('is_featured')
                            ->label(__('bakery.menu.fields.is_featured'))
                            ->helperText(__('bakery.menu.fields.is_featured_help')),
                    ]),
                Section::make(__('bakery.menu.sections.photo'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        // Stored on the public disk beside the seeded photos
                        // (see BakerySeeder). Raster types only -- SVG is a
                        // scriptable document served from our own origin, the
                        // same reason PageResource excludes it.
                        FileUpload::make('image_path')
                            ->label(__('bakery.menu.fields.image'))
                            ->image()
                            ->disk('public')
                            ->directory('menu')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize((int) config('images.max_kilobytes')),
                        TextInput::make('image_credit')
                            ->label(__('bakery.menu.fields.image_credit'))
                            ->helperText(__('bakery.menu.fields.image_credit_help'))
                            ->maxLength(255),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk('public')
                    ->imageWidth(72)
                    ->imageHeight(54),
                TextColumn::make('name')
                    ->label(__('bakery.menu.fields.name'))
                    ->description(fn (MenuItem $record): string => __($record->category->label()))
                    ->searchable(),
                TextColumn::make('price_cents')
                    ->label(__('bakery.menu.fields.price'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->description(fn (MenuItem $record): string => $record->price_unit),
                TextColumn::make('dietary')
                    ->label(__('bakery.menu.fields.dietary'))
                    ->state(fn (MenuItem $record): array => array_map(fn (DietaryTag $tag): string => __($tag->label()), $record->dietaryTags()))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notice_days')
                    ->label(__('bakery.menu.fields.notice_days'))
                    ->formatStateUsing(fn (int $state): string => trans_choice('bakery.menu.notice', $state, ['count' => $state]))
                    ->toggleable(),
                // Switched in place: taking an item off for the week is the
                // most common change, and a modal for one switch is friction.
                // Inline columns skip policies, so the check is made here.
                ToggleColumn::make('is_available')
                    ->label(__('bakery.menu.fields.is_available'))
                    ->disabled(fn (MenuItem $record): bool => ! (auth()->user()?->can('update', $record) ?? false)),
                ToggleColumn::make('is_featured')
                    ->label(__('bakery.menu.fields.is_featured'))
                    ->disabled(fn (MenuItem $record): bool => ! (auth()->user()?->can('update', $record) ?? false))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordAction('edit')
            ->recordUrl(null)
            ->filters([
                SelectFilter::make('category')
                    ->label(__('bakery.menu.fields.category'))
                    ->options(static::categoryOptions()),
                TernaryFilter::make('is_available')
                    ->label(__('bakery.menu.fields.is_available')),
            ])
            ->recordActions([
                // Named 'visit', not 'view': a 'view' action would take over
                // the row click (see PageResource).
                Action::make('visit')
                    ->label(__('bakery.menu.actions.view'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (MenuItem $record): string => sprintf('%s#menu', route('home')))
                    ->openUrlInNewTab()
                    ->iconButton(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMenuItems::route('/'),
        ];
    }

    /**
     * The category choices for the form and the filter.
     *
     * @return array<string, string>
     */
    protected static function categoryOptions(): array
    {
        return collect(MenuCategory::cases())
            ->mapWithKeys(fn (MenuCategory $category): array => [$category->value => __($category->label())])
            ->all();
    }

    /**
     * The dietary note choices for the form.
     *
     * @return array<string, string>
     */
    protected static function dietaryOptions(): array
    {
        return collect(DietaryTag::cases())
            ->mapWithKeys(fn (DietaryTag $tag): array => [$tag->value => __($tag->label())])
            ->all();
    }
}
