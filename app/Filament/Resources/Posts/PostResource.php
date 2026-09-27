<?php

namespace App\Filament\Resources\Posts;

use App\Blog\BlogManager;
use App\Concerns\ImageValidationRules;
use App\Filament\Resources\Posts\Pages\ManagePosts;
use App\Media\MediaCollection;
use App\Media\MediaManager;
use App\Media\StagedUpload;
use App\Models\Post;
use App\Settings\SettingKey;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class PostResource extends Resource
{
    use ImageValidationRules;

    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('blog.posts.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('blog.posts.plural_label');
    }

    /**
     * Hidden, with the rest of the module, until the blog is switched on.
     */
    public static function canAccess(): bool
    {
        return app(BlogManager::class)->enabled() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label(__('blog.posts.fields.title'))
                    ->required()
                    ->maxLength(255)
                    // Fills the slug from the title while creating, so the
                    // common case needs no thought, but never on edit:
                    // changing a live post's title must not silently move
                    // its URL and break every link already shared.
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, string $operation, Set $set): void {
                        if ($operation !== 'create') {
                            return;
                        }

                        $set('slug', Str::slug((string) $state));
                    }),
                TextInput::make('slug')
                    ->label(__('blog.posts.fields.slug'))
                    ->helperText(__('blog.posts.fields.slug_help'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    // Matches the constraint on the post route in
                    // routes/blog.php. A slug the route cannot match would
                    // save happily and then 404, which is the failure this
                    // prevents.
                    ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                    // The record's own slug is exempt, the way ->unique() is
                    // above, so editing a post that already sits on a
                    // reserved slug stays possible -- only a CHANGE to one
                    // is wrong. See PageResource for the same dance.
                    ->notIn(fn (?Post $record): array => array_values(array_diff(
                        Post::RESERVED_SLUGS,
                        [$record?->slug],
                    )))
                    ->validationMessages([
                        'not_in' => __('blog.posts.fields.slug_reserved'),
                        'regex' => __('blog.posts.fields.slug_format'),
                    ]),
                Select::make('category_id')
                    ->label(__('blog.posts.fields.category'))
                    ->helperText(__('blog.posts.fields.category_help'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('tags')
                    ->label(__('blog.posts.fields.tags'))
                    ->helperText(__('blog.posts.fields.tags_help'))
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                MarkdownEditor::make('body')
                    ->label(__('blog.posts.fields.body'))
                    ->helperText(__('blog.posts.fields.body_help'))
                    // The same deliberate trade PageResource makes: body
                    // images upload here, in the editor, and do not go
                    // through App\Media\MediaManager -- the three settings
                    // below are therefore load-bearing, and PageResource's
                    // docblock explains exactly what each one buys.
                    ->fileAttachmentsDirectory('post-body')
                    ->fileAttachmentsAcceptedFileTypes(static::acceptedImageMimeTypes())
                    ->fileAttachmentsMaxSize((int) config('images.max_kilobytes'))
                    ->columnSpanFull(),
                Textarea::make('seo_description')
                    ->label(__('blog.posts.fields.seo_description'))
                    ->helperText(__('blog.posts.fields.seo_description_help'))
                    ->maxLength(255)
                    ->rows(2)
                    ->columnSpanFull(),
                DateTimePicker::make('published_at')
                    ->label(__('blog.posts.fields.published_at'))
                    ->helperText(__('blog.posts.fields.published_at_help'))
                    // Entered in the display timezone the table shows it in;
                    // without this the picker works in UTC and a post
                    // scheduled for 09:00 goes live at 09:00 UTC.
                    ->timezone(fn (): string => app(Settings::class)->string(SettingKey::Timezone))
                    ->seconds(false)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('blog.posts.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('blog.posts.fields.slug'))
                    ->searchable()
                    // Prefixed so the column reads as the path the post is
                    // served at, rather than as a bare word. NOT copyable,
                    // for the reason PageResource's notes.
                    ->formatStateUsing(fn (string $state): string => '/blog/'.$state)
                    ->color('gray'),
                TextColumn::make('status')
                    ->label(__('blog.posts.table.status'))
                    ->state(fn (Post $record): string => $record->status())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'scheduled' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => __("blog.posts.status.{$state}")),
                TextColumn::make('category.name')
                    ->label(__('blog.posts.table.category'))
                    ->sortable(),
                TextColumn::make('author.name')
                    ->label(__('blog.posts.table.author'))
                    ->sortable(),
                // Formatted through the settings service rather than
                // ->dateTime(), so it follows the display timezone, format and
                // locale settings the rest of the site does.
                TextColumn::make('published_at')
                    ->label(__('blog.posts.table.published_at'))
                    ->formatStateUsing(fn ($state): string => $state === null
                        ? '—'
                        : app(Settings::class)->formatDateTime($state))
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            // The cover actions ask hasMedia() for every row; with media
            // eager-loaded that is answered in memory, not two queries a row.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('media'))
            ->filters([
                SelectFilter::make('status')
                    ->label(__('blog.posts.filters.status'))
                    ->options([
                        'draft' => __('blog.posts.status.draft'),
                        'scheduled' => __('blog.posts.status.scheduled'),
                        'published' => __('blog.posts.status.published'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::applyStatusFilter($query, $data['value'] ?? null)),
                SelectFilter::make('category_id')
                    ->label(__('blog.posts.filters.category'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            // Clicking a row opens the edit modal, which is what somebody
            // scanning this table wants to do next -- stated explicitly for
            // the reason PageResource's notes.
            ->recordAction('edit')
            ->recordUrl(null)
            ->recordActions([
                // Opens the live post in a new tab. Named 'visit' rather than
                // 'view' deliberately: it is not a record viewer, and that
                // name is the one ListRecords treats as one. Works on a draft
                // or a scheduled post too, which the controller lets an
                // editor preview -- the only way to see how a post reads
                // before it is live. Hidden on an unpublished post the viewer
                // may not edit, which the controller would answer with a 404.
                Action::make('visit')
                    ->label(__('blog.posts.actions.view'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn (Post $record): bool => $record->isPublished()
                        || (auth()->user()?->can('update', $record) ?? false))
                    ->url(fn (Post $record): string => route('blog.show', $record))
                    ->openUrlInNewTab(),
                // The cover image, attached through its own action rather
                // than a form field: the upload needs a record to own it,
                // and a modal form cannot offer "replace" on a post that
                // does not exist yet. The logo's upload/remove buttons on
                // the Brand tab are the same pattern.
                self::coverAction(),
                Action::make('removeCover')
                    ->label(__('blog.posts.actions.remove_cover'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->authorize('update')
                    ->visible(fn (Post $record): bool => $record->hasMedia(MediaCollection::PostCover))
                    ->requiresConfirmation()
                    ->modalDescription(__('blog.posts.actions.remove_cover_description'))
                    ->action(function (Post $record): void {
                        $record->clearMedia(MediaCollection::PostCover);

                        Notification::make()
                            ->success()
                            ->title(__('blog.posts.actions.cover_removed'))
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // The class-level deleteAny check cannot see ownership,
                    // so each selected post is asked on its own: an editor's
                    // selection silently drops posts somebody else wrote.
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePosts::route('/'),
        ];
    }

    /**
     * Limit a query to the posts in one publishing state.
     *
     * The filter's query closure delegates here because the scopes are only
     * visible to the analyser on the generic Builder -- see the docblock.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public static function applyStatusFilter(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'draft' => $query->draft(),
            'scheduled' => $query->scheduled(),
            'published' => $query->published(),
            default => $query,
        };
    }

    /**
     * The action a post's cover image uploads and replaces through.
     *
     * Like the logo upload on the Brand tab: the file is staged by Filament
     * on the private disk and adopted through MediaManager, which re-encodes
     * it before anything is served. The staged-path guard is load-bearing --
     * dehydrated FileUpload state is client-controllable, and only paths
     * inside the staging directory may ever be adopted (see StagedUpload).
     */
    protected static function coverAction(): Action
    {
        return Action::make('cover')
            ->label(__('blog.posts.actions.cover'))
            ->icon(Heroicon::OutlinedPhoto)
            // A custom action is not authorized by Filament on its own:
            // replacing a cover is an edit, so it needs the edit ability.
            ->authorize('update')
            ->color(fn (Post $record): string => $record->hasMedia(MediaCollection::PostCover) ? 'success' : 'gray')
            ->modalHeading(__('blog.posts.actions.cover_upload_heading'))
            ->form([
                FileUpload::make('cover')
                    ->label(__('blog.posts.actions.cover_field'))
                    ->helperText(__('blog.posts.actions.cover_field_help'))
                    ->rules(app(MediaManager::class)->rulesFor(MediaCollection::PostCover))
                    ->acceptedFileTypes(static::acceptedImageMimeTypes())
                    ->disk('local')
                    ->directory('uploads/pending'),
            ])
            ->action(function (array $data, Post $record): void {
                $sourcePath = (string) ($data['cover'] ?? '');

                if (! StagedUpload::isStagedPath($sourcePath)) {
                    Notification::make()
                        ->danger()
                        ->title(__('blog.posts.actions.cover_rejected_title'))
                        ->body(__('blog.posts.actions.cover_rejected_body'))
                        ->send();

                    return;
                }

                app(MediaManager::class)->attachStagedImage(
                    stagedPath: $sourcePath,
                    collection: MediaCollection::PostCover,
                    owner: $record,
                );

                Notification::make()
                    ->success()
                    ->title(__('blog.posts.actions.cover_uploaded'))
                    ->send();
            });
    }
}
