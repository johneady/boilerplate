<?php

namespace App\Filament\Resources\PerfumeImports;

use App\Filament\Resources\PerfumeImports\Pages\ManagePerfumeImports;
use App\Models\PerfumeImport;
use App\Perfumes\Enums\ImportStatus;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The refresh history, and where a refresh is run from (the page's header
 * action). Read-only: an import is a record of what a file changed.
 */
class PerfumeImportResource extends Resource
{
    protected static ?string $model = PerfumeImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'data-refresh';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('perfumes.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('perfumes.imports.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('perfumes.imports.plural_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    TextEntry::make('file_name')->label(__('perfumes.imports.fields.file_name')),
                    TextEntry::make('status')
                        ->label(__('perfumes.imports.fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (ImportStatus $state): string => __($state->label()))
                        ->color(fn (ImportStatus $state): string => $state->color()),
                    TextEntry::make('finished_at')->label(__('perfumes.imports.fields.finished_at'))->dateTime(),
                    TextEntry::make('rows_created')->label(__('perfumes.imports.fields.rows_created')),
                    TextEntry::make('rows_updated')->label(__('perfumes.imports.fields.rows_updated')),
                    TextEntry::make('rows_failed')->label(__('perfumes.imports.fields.rows_failed')),
                ]),
                KeyValueEntry::make('errors')
                    ->label(__('perfumes.imports.fields.errors'))
                    ->keyLabel(__('perfumes.imports.line'))
                    ->valueLabel(__('perfumes.imports.problem'))
                    ->placeholder(__('perfumes.imports.no_errors'))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->description(__('perfumes.imports.description'))
            ->columns([
                TextColumn::make('file_name')
                    ->label(__('perfumes.imports.fields.file_name'))
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('status')
                    ->label(__('perfumes.imports.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (ImportStatus $state): string => __($state->label()))
                    ->color(fn (ImportStatus $state): string => $state->color()),
                TextColumn::make('rows_created')
                    ->label(__('perfumes.imports.fields.rows_created'))
                    ->numeric()
                    ->color('success'),
                TextColumn::make('rows_updated')
                    ->label(__('perfumes.imports.fields.rows_updated'))
                    ->numeric()
                    ->color('info'),
                TextColumn::make('rows_unchanged')
                    ->label(__('perfumes.imports.fields.rows_unchanged'))
                    ->numeric()
                    ->color('gray'),
                TextColumn::make('rows_failed')
                    ->label(__('perfumes.imports.fields.rows_failed'))
                    ->numeric()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('user.name')
                    ->label(__('perfumes.imports.fields.user'))
                    ->placeholder(__('perfumes.imports.system')),
                TextColumn::make('finished_at')
                    ->label(__('perfumes.imports.fields.finished_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePerfumeImports::route('/'),
        ];
    }
}
