<?php

namespace App\Filament\Resources\Packages;

use App\Filament\Resources\Packages\Pages\ManagePackages;
use App\Models\Package;
use App\Shop\Money;
use App\Shop\Region;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * The catalogue of drone footage packages, with stock managed from the table.
 *
 * Stock and the "on sale" switch are editable inline because they are what
 * changes day to day; everything else lives in the edit modal.
 */
class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('shop.packages.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('shop.packages.plural_label');
    }

    /**
     * The number of limited packages that have sold out, so an empty shelf is
     * noticed from anywhere in the panel.
     */
    public static function getNavigationBadge(): ?string
    {
        $soldOut = Package::query()->active()->where('stock', '<=', 0)->count();

        return $soldOut > 0 ? (string) $soldOut : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('shop.packages.sold_out_badge');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('shop.packages.sections.listing'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->label(__('shop.packages.fields.title'))
                            ->required()
                            ->maxLength(255)
                            // Fills the slug while creating only: changing a
                            // live package's title must not move its URL.
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, string $operation, Set $set): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label(__('shop.packages.fields.slug'))
                            ->helperText(__('shop.packages.fields.slug_help'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']),
                        TextInput::make('location')
                            ->label(__('shop.packages.fields.location'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('country')
                            ->label(__('shop.packages.fields.country'))
                            ->required()
                            ->maxLength(255),
                        Select::make('region')
                            ->label(__('shop.packages.fields.region'))
                            ->options(static::regionOptions())
                            ->required(),
                        TextInput::make('price_cents')
                            ->label(__('shop.packages.fields.price'))
                            ->prefix(Money::currency())
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required()
                            // Stored as integer cents, edited as a decimal.
                            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state / 100, 2, '.', ''))
                            ->dehydrateStateUsing(fn (string|int|float|null $state): int => (int) round(((float) $state) * 100)),
                        TextInput::make('summary')
                            ->label(__('shop.packages.fields.summary'))
                            ->helperText(__('shop.packages.fields.summary_help'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        MarkdownEditor::make('description')
                            ->label(__('shop.packages.fields.description'))
                            ->helperText(__('shop.packages.fields.description_help'))
                            // No attachments: the cover image field below is
                            // the one way an image reaches a package.
                            ->disableToolbarButtons(['attachFiles'])
                            ->columnSpanFull(),
                    ]),
                Section::make(__('shop.packages.sections.footage'))
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('resolution')
                            ->label(__('shop.packages.fields.resolution'))
                            ->required()
                            ->maxLength(32)
                            ->default('4K UHD'),
                        TextInput::make('frame_rate')
                            ->label(__('shop.packages.fields.frame_rate'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(240)
                            ->suffix('fps')
                            ->required()
                            ->default(30),
                        TextInput::make('clip_count')
                            ->label(__('shop.packages.fields.clip_count'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('duration_seconds')
                            ->label(__('shop.packages.fields.duration'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->suffix(__('shop.packages.fields.seconds'))
                            ->required(),
                    ]),
                Section::make(__('shop.packages.sections.availability'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('stock')
                            ->label(__('shop.packages.fields.stock'))
                            ->helperText(__('shop.packages.fields.stock_help'))
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                        Toggle::make('is_active')
                            ->label(__('shop.packages.fields.is_active'))
                            ->helperText(__('shop.packages.fields.is_active_help')),
                        Toggle::make('is_featured')
                            ->label(__('shop.packages.fields.is_featured'))
                            ->helperText(__('shop.packages.fields.is_featured_help')),
                    ]),
                Section::make(__('shop.packages.sections.image'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        // Stored on the public disk beside the seeded covers
                        // (see ShopSeeder). Only raster types -- SVG is a
                        // scriptable document served from our own origin, the
                        // same reason PageResource excludes it.
                        FileUpload::make('image_path')
                            ->label(__('shop.packages.fields.image'))
                            ->image()
                            ->disk('public')
                            ->directory('packages')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize((int) config('images.max_kilobytes')),
                        TextInput::make('image_credit')
                            ->label(__('shop.packages.fields.image_credit'))
                            ->helperText(__('shop.packages.fields.image_credit_help'))
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
                    ->imageWidth(96)
                    ->imageHeight(60),
                TextColumn::make('title')
                    ->label(__('shop.packages.fields.title'))
                    ->description(fn (Package $record): string => $record->location.', '.$record->country)
                    ->searchable(['title', 'location', 'country'])
                    ->sortable(),
                TextColumn::make('region')
                    ->label(__('shop.packages.fields.region'))
                    ->formatStateUsing(fn (Region $state): string => __($state->label()))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('price_cents')
                    ->label(__('shop.packages.fields.price'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),
                // Edited in place: correcting a stock count is the most
                // common change, and a modal for one number is friction.
                // Blank means unlimited, so an emptied cell stores null
                // rather than the 0 that would mean sold out.
                TextInputColumn::make('stock')
                    ->label(__('shop.packages.fields.stock'))
                    ->placeholder(__('shop.packages.fields.unlimited'))
                    ->type('number')
                    ->rules(['nullable', 'integer', 'min:0'])
                    ->updateStateUsing(function (?string $state, Package $record): ?int {
                        $stock = blank($state) ? null : (int) $state;
                        $record->update(['stock' => $stock]);

                        return $stock;
                    })
                    ->disabled(fn (Package $record): bool => ! (auth()->user()?->can('update', $record) ?? false))
                    ->sortable()
                    ->width('8rem'),
                TextColumn::make('order_items_count')
                    ->label(__('shop.packages.fields.sold'))
                    ->counts('orderItems')
                    ->sortable()
                    ->toggleable(),
                ToggleColumn::make('is_active')
                    ->label(__('shop.packages.fields.is_active'))
                    ->disabled(fn (Package $record): bool => ! (auth()->user()?->can('update', $record) ?? false)),
            ])
            ->defaultSort('sort_order')
            ->recordAction('edit')
            ->recordUrl(null)
            ->filters([
                SelectFilter::make('region')
                    ->label(__('shop.packages.fields.region'))
                    ->options(static::regionOptions()),
                TernaryFilter::make('is_active')
                    ->label(__('shop.packages.fields.is_active')),
                Filter::make('low_stock')
                    ->label(__('shop.packages.filters.low_stock'))
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('stock')
                        ->where('stock', '<=', (int) config('shop.low_stock_threshold'))),
            ])
            ->recordActions([
                // Named 'visit', not 'view': see PageResource for why a
                // 'view' action would hijack the row click.
                Action::make('visit')
                    ->label(__('shop.packages.actions.view'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Package $record): string => route('shop.show', $record))
                    ->openUrlInNewTab()
                    ->iconButton(),
                // Icon buttons: the inline stock and on-sale columns already
                // make this table wide, and labelled actions pushed Edit off
                // the edge at laptop widths.
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePackages::route('/'),
        ];
    }

    /**
     * The region choices for the form and the filter.
     *
     * @return array<string, string>
     */
    protected static function regionOptions(): array
    {
        return collect(Region::cases())
            ->mapWithKeys(fn (Region $region): array => [$region->value => __($region->label())])
            ->all();
    }
}
