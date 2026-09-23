<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Models\Vehicle;
use App\Voltiva\Money;
use App\Voltiva\VehicleCategory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * The car range.
 *
 * One form fills the one product template: every field below lands in a
 * fixed place on the car's page and on the compare page, so an editor can
 * add a car, change a price or swap a photo without any design work -- and
 * without being able to break the design.
 */
class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('voltiva.vehicles.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('voltiva.vehicles.plural_label');
    }

    /**
     * The car's public page, for the "view on site" actions.
     */
    public static function publicUrl(Model $record): string
    {
        return route('cars.show', $record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('vehicle')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('voltiva.vehicles.tabs.listing'))
                            ->icon(Heroicon::OutlinedTag)
                            ->columns(2)
                            ->schema(static::listingFields()),
                        Tab::make(__('voltiva.vehicles.tabs.specifications'))
                            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                            ->schema(static::specificationFields()),
                        Tab::make(__('voltiva.vehicles.tabs.selling_points'))
                            ->icon(Heroicon::OutlinedSparkles)
                            ->schema(static::sellingPointFields()),
                        Tab::make(__('voltiva.vehicles.tabs.faq'))
                            ->icon(Heroicon::OutlinedQuestionMarkCircle)
                            ->schema([
                                Repeater::make('faqs')
                                    ->label(__('voltiva.vehicles.fields.faqs'))
                                    ->helperText(__('voltiva.vehicles.fields.faqs_help'))
                                    ->schema([
                                        TextInput::make('question')->label(__('voltiva.vehicles.fields.question'))->required()->maxLength(255),
                                        Textarea::make('answer')->label(__('voltiva.vehicles.fields.answer'))->required()->rows(2),
                                    ])
                                    ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                                    ->collapsible()
                                    ->reorderable()
                                    ->defaultItems(0),
                            ]),
                        Tab::make(__('voltiva.vehicles.tabs.media'))
                            ->icon(Heroicon::OutlinedPhoto)
                            ->columns(2)
                            ->schema(static::mediaFields()),
                        Tab::make(__('voltiva.vehicles.tabs.publishing'))
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->columns(2)
                            ->schema([
                                Toggle::make('is_published')
                                    ->label(__('voltiva.fields.is_published'))
                                    ->helperText(__('voltiva.vehicles.fields.is_published_help')),
                                Toggle::make('is_featured')
                                    ->label(__('voltiva.vehicles.fields.is_featured')),
                                Textarea::make('seo_description')
                                    ->label(__('voltiva.fields.seo_description'))
                                    ->helperText(__('voltiva.fields.seo_description_help'))
                                    ->maxLength(255)
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return list<Component|Field>
     */
    protected static function listingFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('voltiva.vehicles.fields.name'))
                ->required()
                ->maxLength(255)
                // Fills the slug while creating only: renaming a live car
                // must not move its URL.
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, string $operation, Set $set): void {
                    if ($operation === 'create') {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            TextInput::make('slug')
                ->label(__('voltiva.fields.slug'))
                ->helperText(__('voltiva.fields.slug_help'))
                ->prefix('/cars/')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                // l6e and l7e are the category listings' addresses.
                ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(Vehicle::RESERVED_SLUGS)]),
            Select::make('category')
                ->label(__('voltiva.vehicles.fields.category'))
                ->helperText(__('voltiva.vehicles.fields.category_help'))
                ->options(collect(VehicleCategory::cases())->mapWithKeys(
                    fn (VehicleCategory $category): array => [$category->value => $category->label().' – '.$category->description()],
                )->all())
                ->required(),
            TextInput::make('tagline')
                ->label(__('voltiva.vehicles.fields.tagline'))
                ->helperText(__('voltiva.vehicles.fields.tagline_help'))
                ->required()
                ->maxLength(255),
            static::moneyInput('price_cents', __('voltiva.vehicles.fields.price'))->required(),
            static::moneyInput('monthly_from_cents', __('voltiva.vehicles.fields.monthly_from'))
                ->helperText(__('voltiva.vehicles.fields.monthly_from_help')),
            Textarea::make('summary')
                ->label(__('voltiva.vehicles.fields.summary'))
                ->helperText(__('voltiva.vehicles.fields.summary_help'))
                ->required()
                ->maxLength(500)
                ->rows(2)
                ->columnSpanFull(),
            MarkdownEditor::make('description')
                ->label(__('voltiva.vehicles.fields.description'))
                ->helperText(__('voltiva.fields.markdown_help'))
                // Photos go in the Photos & video tab, into fixed areas.
                ->disableToolbarButtons(['attachFiles'])
                ->columnSpanFull(),
        ];
    }

    /**
     * @return list<Component>
     */
    protected static function specificationFields(): array
    {
        $number = fn (string $name, string $label, string $suffix, bool $decimal = false): TextInput => TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->step($decimal ? 0.1 : 1)
            ->suffix($suffix)
            ->required();

        return [
            Section::make(__('voltiva.vehicles.sections.performance'))
                ->description(__('voltiva.vehicles.sections.performance_help'))
                ->columns(4)
                ->schema([
                    $number('top_speed_kmh', __('voltiva.vehicles.fields.top_speed_kmh'), 'km/h'),
                    $number('range_km', __('voltiva.vehicles.fields.range_km'), 'km'),
                    $number('motor_kw', __('voltiva.vehicles.fields.motor_kw'), 'kW', true),
                    $number('charge_hours', __('voltiva.vehicles.fields.charge_hours'), 'h', true),
                ]),
            Section::make(__('voltiva.vehicles.sections.battery'))
                ->columns(4)
                ->schema([
                    $number('battery_voltage', __('voltiva.vehicles.fields.battery_voltage'), 'V'),
                    $number('battery_capacity_ah', __('voltiva.vehicles.fields.battery_capacity_ah'), 'Ah'),
                    $number('battery_kwh', __('voltiva.vehicles.fields.battery_kwh'), 'kWh', true)
                        ->helperText(__('voltiva.vehicles.fields.battery_kwh_help')),
                    TextInput::make('battery_chemistry')
                        ->label(__('voltiva.vehicles.fields.battery_chemistry'))
                        ->default('LiFePO4')
                        ->required()
                        ->maxLength(50),
                ]),
            Section::make(__('voltiva.vehicles.sections.size'))
                ->columns(5)
                ->schema([
                    $number('seats', __('voltiva.vehicles.fields.seats'), ''),
                    $number('length_mm', __('voltiva.vehicles.fields.length_mm'), 'mm'),
                    $number('width_mm', __('voltiva.vehicles.fields.width_mm'), 'mm'),
                    $number('height_mm', __('voltiva.vehicles.fields.height_mm'), 'mm'),
                    $number('kerb_weight_kg', __('voltiva.vehicles.fields.kerb_weight_kg'), 'kg'),
                ]),
            Section::make(__('voltiva.vehicles.sections.warranty'))
                ->columns(4)
                ->schema([
                    $number('warranty_years', __('voltiva.vehicles.fields.warranty_years'), __('voltiva.vehicles.fields.years'))->default(2),
                    $number('battery_warranty_years', __('voltiva.vehicles.fields.battery_warranty_years'), __('voltiva.vehicles.fields.years'))->default(5),
                ]),
        ];
    }

    /**
     * @return list<Component|Field>
     */
    protected static function sellingPointFields(): array
    {
        return [
            Repeater::make('key_benefits')
                ->label(__('voltiva.vehicles.fields.key_benefits'))
                ->helperText(__('voltiva.vehicles.fields.key_benefits_help'))
                ->schema([
                    TextInput::make('title')->label(__('voltiva.vehicles.fields.benefit_title'))->required()->maxLength(80),
                    Textarea::make('body')->label(__('voltiva.vehicles.fields.benefit_body'))->required()->rows(2)->maxLength(300),
                ])
                ->grid(2)
                ->maxItems(4)
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->reorderable()
                ->defaultItems(0),
            Repeater::make('equipment')
                ->label(__('voltiva.vehicles.fields.equipment'))
                ->helperText(__('voltiva.vehicles.fields.equipment_help'))
                ->simple(TextInput::make('item')->required()->maxLength(120))
                ->grid(2)
                ->reorderable()
                ->defaultItems(0),
            Grid::make(2)->schema([
                Textarea::make('comfort')
                    ->label(__('voltiva.vehicles.fields.comfort'))
                    ->rows(4),
                Textarea::make('safety')
                    ->label(__('voltiva.vehicles.fields.safety'))
                    ->rows(4),
            ]),
        ];
    }

    /**
     * @return list<Field>
     */
    protected static function mediaFields(): array
    {
        // Stored on the public disk beside the seeded photography. Raster
        // types only -- SVG is a scriptable document served from our own
        // origin, the reason PageResource excludes it too.
        $upload = fn (string $name): FileUpload => FileUpload::make($name)
            ->image()
            ->disk('public')
            ->directory('vehicles')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize((int) config('images.max_kilobytes'));

        return [
            $upload('image_path')
                ->label(__('voltiva.vehicles.fields.image'))
                ->helperText(__('voltiva.vehicles.fields.image_help')),
            $upload('gallery')
                ->label(__('voltiva.vehicles.fields.gallery'))
                ->helperText(__('voltiva.vehicles.fields.gallery_help'))
                ->multiple()
                ->reorderable()
                ->maxFiles(4),
            TextInput::make('video_url')
                ->label(__('voltiva.vehicles.fields.video_url'))
                ->helperText(__('voltiva.vehicles.fields.video_url_help'))
                ->url()
                ->maxLength(255)
                ->rules(['nullable', 'regex:~^https://(www\.)?(youtube\.com|youtu\.be|vimeo\.com)/~']),
            TextInput::make('image_credit')
                ->label(__('voltiva.fields.image_credit'))
                ->helperText(__('voltiva.fields.image_credit_help'))
                ->maxLength(255),
        ];
    }

    /**
     * A price field: stored as integer cents, edited as whole euros.
     */
    protected static function moneyInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->prefix('€')
            ->numeric()
            ->minValue(0)
            ->step(1)
            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : (string) intdiv($state, 100))
            ->dehydrateStateUsing(fn (string|int|float|null $state): ?int => blank($state) ? null : (int) round(((float) $state) * 100));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk('public')
                    ->imageWidth(96)
                    ->imageHeight(64),
                TextColumn::make('name')
                    ->label(__('voltiva.vehicles.fields.name'))
                    ->description(fn (Vehicle $record): string => $record->tagline)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label(__('voltiva.vehicles.fields.category'))
                    ->formatStateUsing(fn (VehicleCategory $state): string => $state->label())
                    ->badge()
                    ->color(fn (VehicleCategory $state): string => $state === VehicleCategory::L6e ? 'info' : 'primary'),
                TextColumn::make('price_cents')
                    ->label(__('voltiva.vehicles.fields.price'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),
                TextColumn::make('range_km')
                    ->label(__('voltiva.vehicles.fields.range_km'))
                    ->suffix(' km')
                    ->sortable(),
                TextColumn::make('top_speed_kmh')
                    ->label(__('voltiva.vehicles.fields.top_speed_kmh'))
                    ->suffix(' km/h')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('enquiries_count')
                    ->label(__('voltiva.vehicles.fields.enquiries'))
                    ->counts('enquiries')
                    ->sortable(),
                ToggleColumn::make('is_published')
                    ->label(__('voltiva.fields.is_published'))
                    ->disabled(fn (Vehicle $record): bool => ! (auth()->user()?->can('update', $record) ?? false)),
            ])
            ->defaultSort('sort_order')
            // Drag to set the order cars appear in on the site. Reordering
            // writes every row, so it needs the update permission itself --
            // Filament checks nothing beyond reaching the table.
            ->reorderable('sort_order')
            ->authorizeReorder(fn (): bool => auth()->user()?->can('update', Vehicle::class) ?? false)
            ->filters([
                SelectFilter::make('category')
                    ->label(__('voltiva.vehicles.fields.category'))
                    ->options(collect(VehicleCategory::cases())->mapWithKeys(
                        fn (VehicleCategory $category): array => [$category->value => $category->label()],
                    )->all()),
            ])
            ->recordActions([
                // Named 'visit', not 'view': see PageResource for why a
                // 'view' action would hijack the row click.
                Action::make('visit')
                    ->label(__('voltiva.actions.view_on_site'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Vehicle $record): string => static::publicUrl($record))
                    ->openUrlInNewTab()
                    ->iconButton(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
