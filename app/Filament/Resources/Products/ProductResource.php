<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Product;
use App\Ordering\Price;
use App\Ordering\ProductCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
 * The café's menu: what customers see on the home page and can add to their
 * cart.
 *
 * "On the menu" is switched straight from the table, because sold-out is the
 * change made most often. Rows are dragged into menu order. Everything else
 * is in the edit modal.
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('ordering.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('ordering.products.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ordering.products.plural_label');
    }

    /**
     * How many products are switched off, so a gap in the menu is noticed.
     */
    public static function getNavigationBadge(): ?string
    {
        $off = Product::query()->where('is_available', false)->count();

        return $off > 0 ? (string) $off : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('ordering.products.unavailable_badge');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('ordering.products.sections.product'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('ordering.products.fields.name'))
                            ->required()
                            ->maxLength(255)
                            // Fills the slug on create only, so renaming a
                            // product never changes its slug.
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, string $operation, Set $set): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label(__('ordering.products.fields.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']),
                        Select::make('category')
                            ->label(__('ordering.products.fields.category'))
                            ->options(ProductCategory::options())
                            ->required(),
                        TextInput::make('price_cents')
                            ->label(__('ordering.products.fields.price'))
                            ->prefix('$')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            // Stored as integer cents, edited as dollars.
                            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state / 100, 2, '.', ''))
                            ->dehydrateStateUsing(fn (string|int|float|null $state): int => (int) round(((float) $state) * 100)),
                        Textarea::make('description')
                            ->label(__('ordering.products.fields.description'))
                            ->required()
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                        Toggle::make('is_available')
                            ->label(__('ordering.products.fields.is_available'))
                            ->helperText(__('ordering.products.fields.is_available_help'))
                            ->default(true),
                        Toggle::make('is_featured')
                            ->label(__('ordering.products.fields.is_featured'))
                            ->helperText(__('ordering.products.fields.is_featured_help')),
                    ]),
                Section::make(__('ordering.products.sections.photo'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        // Raster types only: SVG is a scriptable document
                        // served from our own origin.
                        FileUpload::make('image_path')
                            ->label(__('ordering.products.fields.image'))
                            ->image()
                            ->disk('public')
                            ->directory('menu')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize((int) config('images.max_kilobytes')),
                        TextInput::make('image_credit')
                            ->label(__('ordering.products.fields.image_credit'))
                            ->helperText(__('ordering.products.fields.image_credit_help'))
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
                    ->imageWidth(64)
                    ->imageHeight(48),
                TextColumn::make('name')
                    ->label(__('ordering.products.fields.name'))
                    ->description(fn (Product $record): string => __($record->category->label()))
                    ->searchable(),
                TextColumn::make('price_cents')
                    ->label(__('ordering.products.fields.price'))
                    ->formatStateUsing(fn (int $state): string => Price::format($state))
                    ->sortable(),
                // Switched in place; inline columns skip policies, so the
                // check is made here.
                ToggleColumn::make('is_available')
                    ->label(__('ordering.products.fields.is_available'))
                    ->disabled(fn (Product $record): bool => ! (auth()->user()?->can('update', $record) ?? false)),
                ToggleColumn::make('is_featured')
                    ->label(__('ordering.products.fields.is_featured'))
                    ->disabled(fn (Product $record): bool => ! (auth()->user()?->can('update', $record) ?? false)),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordAction('edit')
            ->recordUrl(null)
            ->filters([
                SelectFilter::make('category')
                    ->label(__('ordering.products.fields.category'))
                    ->options(ProductCategory::options()),
                TernaryFilter::make('is_available')
                    ->label(__('ordering.products.fields.is_available')),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProducts::route('/'),
        ];
    }
}
