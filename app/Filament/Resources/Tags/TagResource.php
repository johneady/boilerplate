<?php

namespace App\Filament\Resources\Tags;

use App\Blog\BlogManager;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('blog.tags.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('blog.tags.plural_label');
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
                TextInput::make('name')
                    ->label(__('blog.tags.fields.name'))
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, string $operation, Set $set): void {
                        if ($operation !== 'create') {
                            return;
                        }

                        $set('slug', Str::slug((string) $state));
                    }),
                TextInput::make('slug')
                    ->label(__('blog.tags.fields.slug'))
                    ->helperText(__('blog.tags.fields.slug_help'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                    ->validationMessages([
                        'regex' => __('blog.tags.fields.slug_format'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('blog.tags.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('blog.tags.fields.slug'))
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => '/blog/tag/'.$state)
                    ->color('gray'),
                TextColumn::make('posts_count')
                    ->label(__('blog.tags.fields.posts'))
                    ->counts('posts')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordAction('edit')
            ->recordUrl(null)
            ->recordActions([
                Action::make('visit')
                    ->label(__('blog.tags.actions.view'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Tag $record): string => route('blog.tag', $record))
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
            'index' => ManageTags::route('/'),
        ];
    }
}
