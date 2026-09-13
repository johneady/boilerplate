<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\ManagePages;
use App\Models\Page;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('pages.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pages.resource.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label(__('pages.fields.title'))
                    ->required()
                    ->maxLength(255)
                    // Fills the slug from the title while creating, so the common
                    // case needs no thought, but never on edit: changing a live
                    // page's title must not silently move its URL and break
                    // every link anybody has already shared.
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, string $operation, Set $set): void {
                        if ($operation !== 'create') {
                            return;
                        }

                        $set('slug', Str::slug((string) $state));
                    }),
                TextInput::make('slug')
                    ->label(__('pages.fields.slug'))
                    ->helperText(__('pages.fields.slug_help'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    // Matches the constraint on the catch-all route in
                    // routes/web.php. A slug the route cannot match would save
                    // happily and then 404, which is the failure this prevents.
                    ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                    // The record's own slug is exempt, the way ->unique() is
                    // above. PagesSeeder ships a 'contact' page -- deliberately,
                    // because that row supplies the copy above the contact form
                    // -- and 'contact' is reserved, so a flat notIn() made the
                    // seeded page permanently unsaveable: an administrator
                    // editing only its title was rejected on a slug field they
                    // never touched. Only a CHANGE to a reserved slug is wrong.
                    ->notIn(fn (?Page $record): array => array_values(array_diff(
                        Page::RESERVED_SLUGS,
                        [$record?->slug],
                    )))
                    ->validationMessages([
                        'not_in' => __('pages.fields.slug_reserved'),
                        'regex' => __('pages.fields.slug_format'),
                    ]),
                MarkdownEditor::make('body')
                    ->label(__('pages.fields.body'))
                    ->helperText(__('pages.fields.body_help'))
                    ->columnSpanFull(),
                Textarea::make('seo_description')
                    ->label(__('pages.fields.seo_description'))
                    ->helperText(__('pages.fields.seo_description_help'))
                    ->maxLength(255)
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->label(__('pages.fields.is_published'))
                    ->helperText(__('pages.fields.is_published_help')),
                Toggle::make('show_in_footer')
                    ->label(__('pages.fields.show_in_footer'))
                    ->helperText(__('pages.fields.show_in_footer_help'))
                    ->default(true),
                TextInput::make('sort_order')
                    ->label(__('pages.fields.sort_order'))
                    ->helperText(__('pages.fields.sort_order_help'))
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(9999),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('pages.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('pages.fields.slug'))
                    ->searchable()
                    ->copyable()
                    // Prefixed with a slash so the column reads as the path the
                    // page is served at rather than as a bare word.
                    ->formatStateUsing(fn (string $state): string => '/'.$state)
                    ->color('gray'),
                IconColumn::make('is_published')
                    ->label(__('pages.fields.is_published'))
                    ->boolean()
                    ->sortable(),
                IconColumn::make('show_in_footer')
                    ->label(__('pages.fields.show_in_footer'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')
                    ->label(__('pages.fields.sort_order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Formatted through the settings service rather than
                // ->dateTime(), so it follows the display timezone, format and
                // locale settings the rest of the site does.
                TextColumn::make('updated_at')
                    ->label(__('pages.fields.updated'))
                    ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state))
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            // Clicking a row opens the edit modal, which is what somebody
            // scanning this table wants to do next.
            //
            // Stated explicitly rather than left to ListRecords, whose default
            // picks the first of the actions named 'view' or 'edit' that has a
            // URL and makes the whole row a link to it. The action below used to
            // be named 'view', so it was chosen instead and every row click left
            // the panel for the public page.
            ->recordAction('edit')
            ->recordUrl(null)
            ->filters([
                TernaryFilter::make('is_published')
                    ->label(__('pages.fields.is_published')),
            ])
            ->recordActions([
                // Opens the live page in a new tab. Named 'visit' rather than
                // 'view' deliberately: it is not a record viewer, and that name
                // is the one ListRecords treats as one (see above).
                //
                // Works on a draft too: the controller lets anybody who may edit
                // a page preview it, which is the only way to see how it reads
                // before publishing.
                Action::make('visit')
                    ->label(__('pages.actions.view'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Page $record): string => route('pages.show', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePages::route('/'),
        ];
    }
}
