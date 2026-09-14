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
use Filament\Forms\Components\Hidden;
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
                    // Body images are uploaded here, in the editor, and
                    // DELIBERATELY do not go through App\Media\MediaManager:
                    // an author adding an image while writing is worth more
                    // than the media library's bookkeeping for this one case.
                    //
                    // Know what that costs, because nothing else enforces it.
                    // Filament stores these itself and only ever publicly (the
                    // docs are explicit that temporary URLs are unsupported for
                    // static content), so they are served EXACTLY AS UPLOADED:
                    // no re-encoding, which means EXIF -- including GPS on a
                    // phone photo -- survives into a public URL. No media row
                    // describes them, so the orphan prune cannot see them and
                    // a file stays on disk after the body stops referencing it.
                    //
                    // The three settings below are therefore the only controls
                    // on this path, and each is load-bearing:
                    //  - a directory, so the files are identifiable rather than
                    //    loose at the public disk root
                    //  - an accepted-type list that excludes SVG, which is a
                    //    scriptable document served from our own origin
                    //  - a size ceiling, since no validator elsewhere runs
                    ->fileAttachmentsDirectory('page-body')
                    ->fileAttachmentsAcceptedFileTypes(static::attachmentMimeTypes())
                    ->fileAttachmentsMaxSize((int) config('images.max_kilobytes'))
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
                // Footer order is dragged rather than typed, so this carries
                // the value instead of a visible field -- and it must not be
                // left to the column default.
                //
                // That default is 0, which sorts BEFORE every existing page:
                // without this, a page created with "Link in footer" on would
                // silently become the FIRST link in the public footer, and a
                // second one would land on 0 as well and collide with it. New
                // rows belong at the end, where somebody can then drag them.
                Hidden::make('sort_order')
                    ->default(fn (): int => ((int) Page::max('sort_order')) + 1)
                    ->dehydrated(fn (string $operation): bool => $operation === 'create'),
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
                    // NOT copyable: copyable() intercepts the click on this
                    // cell to copy the text, which swallows the row's own
                    // recordAction and stops the edit modal opening -- the
                    // whole row should behave the same way, and editing is what
                    // clicking a page is for. The slug is visible to select by
                    // hand, and the edit form has it as a field.
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
            // Footer order is set by dragging rows here rather than by typing a
            // number into the edit form, which is why that field is gone.
            //
            // Dragging rewrites sort_order as a dense 1..n sequence over the
            // rows on screen, so the "pages sharing a number" case the old
            // numeric field allowed stops arising for anything reordered this
            // way. Page::inFooter() still breaks a tie on title, because the
            // seeded rows keep the numbers they have until somebody drags them.
            //
            // A page created since gets max+1 from the hidden field in the
            // form above rather than the column's own default of 0, which
            // would have put every new page FIRST in the public footer and
            // collided every new page with the last one.
            //
            // The condition is why reordering is refused while a search or
            // filter is active, and it is not cosmetic. reorderTable() rewrites
            // ONLY the keys it is handed -- the rows matching the current
            // filter -- to 1..n, with no regard for the rows it cannot see. So
            // filtering to the two drafts of A(1) B(2) C(3) D(4) and dragging
            // them leaves A=1 D=1 B=2 C=2: colliding numbers across the whole
            // table, and a public footer silently ordered by title instead of
            // by the drag. Dense renumbering is only safe over the full set.
            //
            // authorizeReorder() is not decoration either: reorderTable() is a
            // public Livewire method that writes straight to the column, and
            // Filament leaves it AUTHORIZED BY DEFAULT -- it consults no policy
            // of its own. Without this line, any account that can reach this
            // table could reorder it, including one holding only ViewPages.
            ->reorderable('sort_order', condition: fn (Table $table): bool => ! $table->hasSearch()
                && ! $table->isFiltered())
            ->authorizeReorder(fn (): bool => auth()->user()?->can('update', Page::class) ?? false)
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

    /**
     * The mime types the body editor accepts for an attachment.
     *
     * Derived from config('images.accepted_extensions') rather than listed
     * again, so the editor cannot drift from what the rest of the application
     * accepts. SVG is absent from that list on purpose and must stay absent --
     * it is a scriptable document served from this application's own origin.
     *
     * @return array<int, string>
     */
    protected static function attachmentMimeTypes(): array
    {
        /** @var array<int, string> $extensions */
        $extensions = config('images.accepted_extensions');

        return array_values(array_unique(array_map(
            fn (string $extension): string => 'image/'.($extension === 'jpg' ? 'jpeg' : $extension),
            $extensions,
        )));
    }
}
