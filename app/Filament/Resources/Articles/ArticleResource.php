<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Settings\Settings;
use App\Voltiva\ArticleTopic;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * News & Advice.
 *
 * Every article renders through the one article template; the editor picks
 * a topic (which section the article links back to) and optionally a car
 * (whose card appears at the foot), so publishing needs no designer.
 */
class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('voltiva.articles.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('voltiva.articles.plural_label');
    }

    /**
     * The article's public page, for the "view on site" actions.
     */
    public static function publicUrl(Model $record): string
    {
        return route('news.show', $record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('voltiva.articles.sections.article'))
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('voltiva.articles.fields.title'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, string $operation, Set $set): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label(__('voltiva.fields.slug'))
                            ->helperText(__('voltiva.fields.slug_help'))
                            ->prefix('/news/')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']),
                        Textarea::make('excerpt')
                            ->label(__('voltiva.articles.fields.excerpt'))
                            ->helperText(__('voltiva.articles.fields.excerpt_help'))
                            ->required()
                            ->maxLength(500)
                            ->rows(2),
                        MarkdownEditor::make('body')
                            ->label(__('voltiva.articles.fields.body'))
                            ->helperText(__('voltiva.fields.markdown_help'))
                            ->required()
                            // The cover photo is the article's one image, in a
                            // fixed area; see the Photos section.
                            ->disableToolbarButtons(['attachFiles']),
                    ]),
                Section::make(__('voltiva.articles.sections.details'))
                    ->columnSpan(1)
                    ->schema([
                        Select::make('topic')
                            ->label(__('voltiva.articles.fields.topic'))
                            ->helperText(__('voltiva.articles.fields.topic_help'))
                            ->options(collect(ArticleTopic::cases())->mapWithKeys(
                                fn (ArticleTopic $topic): array => [$topic->value => $topic->label()],
                            )->all())
                            ->required(),
                        Select::make('vehicle_id')
                            ->label(__('voltiva.articles.fields.vehicle'))
                            ->helperText(__('voltiva.articles.fields.vehicle_help'))
                            ->relationship('vehicle', 'name')
                            ->preload(),
                        FileUpload::make('image_path')
                            ->label(__('voltiva.articles.fields.image'))
                            ->image()
                            ->disk('public')
                            ->directory('articles')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize((int) config('images.max_kilobytes')),
                        TextInput::make('image_credit')
                            ->label(__('voltiva.fields.image_credit'))
                            ->maxLength(255),
                        Toggle::make('is_published')
                            ->label(__('voltiva.fields.is_published')),
                        DateTimePicker::make('published_at')
                            ->label(__('voltiva.articles.fields.published_at'))
                            ->helperText(__('voltiva.articles.fields.published_at_help'))
                            ->default(now()),
                        Textarea::make('seo_description')
                            ->label(__('voltiva.fields.seo_description'))
                            ->helperText(__('voltiva.fields.seo_description_help'))
                            ->maxLength(255)
                            ->rows(3),
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
                    ->imageHeight(64),
                TextColumn::make('title')
                    ->label(__('voltiva.articles.fields.title'))
                    ->description(fn (Article $record): string => Str::limit($record->excerpt, 80))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('topic')
                    ->label(__('voltiva.articles.fields.topic'))
                    ->formatStateUsing(fn (ArticleTopic $state): string => $state->label())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('vehicle.name')
                    ->label(__('voltiva.articles.fields.vehicle'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->label(__('voltiva.articles.fields.published_at'))
                    ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDate($state))
                    ->sortable(),
                ToggleColumn::make('is_published')
                    ->label(__('voltiva.fields.is_published'))
                    ->disabled(fn (Article $record): bool => ! (auth()->user()?->can('update', $record) ?? false)),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('topic')
                    ->label(__('voltiva.articles.fields.topic'))
                    ->options(collect(ArticleTopic::cases())->mapWithKeys(
                        fn (ArticleTopic $topic): array => [$topic->value => $topic->label()],
                    )->all()),
            ])
            ->recordActions([
                Action::make('visit')
                    ->label(__('voltiva.actions.view_on_site'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Article $record): string => static::publicUrl($record))
                    ->openUrlInNewTab()
                    ->iconButton(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }
}
