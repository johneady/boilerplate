<?php

namespace App\Filament\Resources\Media;

use App\Filament\Resources\Media\Pages\ListMedia;
use App\Media\MediaCollection;
use App\Models\Media;
use App\Settings\SettingKey;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * The library view of every stored file.
 *
 * There is no create or edit action, and that is deliberate rather than
 * unfinished -- MediaPolicy denies both to everyone:
 *
 * - Uploading goes through App\Media\MediaManager, which validates the bytes,
 *   stages them privately and re-encodes images. A create form here would be a
 *   second way in that skipped all of it.
 * - A row is a statement about a file on disk. Editing one cannot move the
 *   bytes, so it could only ever make the row disagree with the file.
 *
 * Deleting IS offered: Media::delete() removes the files before the row, so it
 * is the one operation that keeps the two in step.
 *
 * The list's main job is answering "what is on the disk, and what is it
 * attached to" -- including the orphans the hourly prune is about to collect,
 * which nothing else in the panel makes visible.
 */
class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('media.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('media.resource.plural_label');
    }

    /**
     * Eager-load the relations every row renders.
     *
     * Without the uploader here the table is one query per row for the name,
     * and `model` is a morph, so its rows come back grouped per type rather
     * than one at a time.
     */
    public static function getEloquentQuery(): Builder
    {
        // `model` is deliberately NOT eager-loaded. A row naming a class a
        // later release removed makes Eloquent throw Error the moment the
        // relation is touched, and eager-loading touches every row -- so one
        // stale row would 500 the whole library rather than render as unknown.
        // describeOwner() reads the stored columns instead, which always work.
        return parent::getEloquentQuery()->with('uploader');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('preview')
                    ->label(__('media.fields.preview'))
                    ->state(fn (Media $record): ?string => static::absoluteUrl($record))
                    // Only an image that has finished processing has anything
                    // to show; a document and an in-flight upload have not.
                    ->visible(fn (Media $record): bool => static::absoluteUrl($record) !== null)
                    ->columnSpanFull(),
                TextEntry::make('file_name')
                    ->label(__('media.fields.file_name')),
                TextEntry::make('collection')
                    ->label(__('media.fields.collection'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::collectionLabel($state)),
                TextEntry::make('status')
                    ->label(__('media.fields.status'))
                    ->badge()
                    ->state(fn (Media $record): string => static::statusLabel($record))
                    ->color(fn (Media $record): string => static::statusColour($record)),
                TextEntry::make('model_type')
                    ->label(__('media.fields.owner'))
                    ->state(fn (Media $record): string => static::describeOwner($record))
                    // An orphan is about to be deleted by the hourly prune, so
                    // say so rather than showing a bare dash.
                    ->hint(fn (Media $record): ?string => $record->hasResolvableOwner()
                        ? null
                        : static::orphanWarning())
                    ->hintColor('warning'),
                TextEntry::make('uploader.name')
                    ->label(__('media.fields.uploader'))
                    // The foreign key goes null when the account is deleted, so
                    // the relation resolves to nothing for older files.
                    ->placeholder('—'),
                TextEntry::make('mime_type')
                    ->label(__('media.fields.mime_type')),
                TextEntry::make('size')
                    ->label(__('media.fields.size'))
                    ->state(fn (Media $record): string => $record->humanSize()),
                TextEntry::make('dimensions')
                    ->label(__('media.fields.dimensions'))
                    ->state(fn (Media $record): ?string => $record->width === null
                        ? null
                        : "{$record->width} × {$record->height}")
                    ->placeholder('—'),
                TextEntry::make('created_at')
                    ->label(__('media.fields.uploaded'))
                    ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state)),
                TextEntry::make('disk')
                    ->label(__('media.fields.disk')),
                TextEntry::make('path')
                    ->label(__('media.fields.path'))
                    ->columnSpanFull(),
                KeyValueEntry::make('conversions')
                    ->label(__('media.fields.conversions'))
                    ->keyLabel(__('media.fields.collection'))
                    ->valueLabel(__('media.fields.path'))
                    ->visible(fn (Media $record): bool => $record->isImage())
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview')
                    ->label(__('media.fields.preview'))
                    // Passed through as an ABSOLUTE url: ImageColumn treats
                    // anything else as a path on its own disk, fails the
                    // existence check, and silently renders nothing for every
                    // file that actually has a conversion. Same trap as the
                    // users table.
                    ->state(fn (Media $record): ?string => static::absoluteUrl($record))
                    ->defaultImageUrl(null)
                    ->square(),
                TextColumn::make('file_name')
                    ->label(__('media.fields.file_name'))
                    ->searchable()
                    ->description(fn (Media $record): string => $record->mime_type),
                TextColumn::make('collection')
                    ->label(__('media.fields.collection'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::collectionLabel($state))
                    ->sortable(),
                TextColumn::make('model_type')
                    ->label(__('media.fields.owner'))
                    ->state(fn (Media $record): string => static::describeOwner($record))
                    ->color(fn (Media $record): ?string => $record->hasResolvableOwner() ? null : 'warning'),
                TextColumn::make('uploader.name')
                    ->label(__('media.fields.uploader'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('size')
                    ->label(__('media.fields.size'))
                    ->state(fn (Media $record): string => $record->humanSize())
                    // Sorted on the raw column, not the formatted string --
                    // "9 KB" sorts after "10 MB" alphabetically.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('size', $direction === 'desc' ? 'desc' : 'asc')),
                TextColumn::make('created_at')
                    ->label(__('media.fields.uploaded'))
                    ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('collection')
                    ->label(__('media.fields.collection'))
                    ->options(fn (): array => collect(MediaCollection::cases())
                        ->mapWithKeys(fn (MediaCollection $c): array => [
                            $c->value => static::collectionLabel($c->value),
                        ])
                        ->all())
                    ->multiple(),
                Filter::make('orphaned')
                    ->label(__('media.filters.orphaned'))
                    // The question the prune answers, asked by hand: what is on
                    // the disk that nothing points at any more.
                    //
                    // Delegates to Media's own scope rather than restating the
                    // condition. Restating it is how the filter came to disagree
                    // with the prune once already: the scope learned to treat a
                    // removed model class as orphaned and this copy did not, so
                    // the panel hid rows the prune was about to delete.
                    ->query(fn (Builder $query): Builder => Media::scopeToOrphaned($query))
                    ->toggle(),
                Filter::make('images')
                    ->label(__('media.filters.images'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('conversions'))
                    ->toggle(),
                Filter::make('documents')
                    ->label(__('media.filters.documents'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('conversion_set'))
                    ->toggle(),
                Filter::make('uploaded_at')
                    ->schema([
                        DatePicker::make('from')->label(__('media.filters.from')),
                        DatePicker::make('until')->label(__('media.filters.until')),
                    ])
                    // Half-open instant range rather than whereDate(), for the
                    // reason AuditLogResource documents at length: the column
                    // is UTC but the table renders in the display timezone, so
                    // comparing the raw date files a row under a different day
                    // than the one it is shown as.
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query
                                ->where('created_at', '>=', static::displayDayStart($date)),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query
                                ->where('created_at', '<', static::displayDayStart($date, 1)),
                        )),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('download')
                    ->label(__('media.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    // Private files leave through the signed route, where the
                    // owning record's policy is checked -- never as a direct
                    // disk URL, which would bypass authorization entirely.
                    ->url(fn (Media $record): ?string => static::downloadUrl($record), shouldOpenInNewTab: true)
                    ->visible(fn (Media $record): bool => static::downloadUrl($record) !== null),
                DeleteAction::make()
                    ->modalHeading(__('media.delete.heading'))
                    ->modalDescription(static::deleteWarning())
                    ->modalSubmitActionLabel(__('media.delete.confirm')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading(__('media.delete.heading_bulk'))
                        ->modalDescription(static::deleteWarning())
                        ->modalSubmitActionLabel(__('media.delete.confirm_bulk')),
                ]),
            ])
            ->emptyStateHeading(__('media.empty.heading'))
            ->emptyStateDescription(__('media.empty.description'))
            ->emptyStateIcon(Heroicon::OutlinedPhoto);
    }

    /**
     * The delete confirmation warning, as one screen-reader-friendly block.
     *
     * A view rather than a string assembled here: markup concatenated onto
     * __() calls is what TranslationsTest forbids, and the two sentences carry
     * different weights. Filament gives a confirmation modal the `alertdialog`
     * role and reads its description aloud on open, so the warning belongs in
     * the description -- anything rendered elsewhere is not announced.
     */
    protected static function deleteWarning(): HtmlString
    {
        return new HtmlString(view('filament.media.delete-warning')->render());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
        ];
    }

    /**
     * A URL the panel can render an <img> from, or null.
     *
     * Absolute, because ImageColumn and ImageEntry treat a root-relative string
     * as a path on their own disk rather than a URL. Null for a document and
     * for an image still being processed -- both have nothing to show, and a
     * broken thumbnail is worse than none.
     */
    protected static function absoluteUrl(Media $record): ?string
    {
        $url = $record->url();

        return $url === null ? null : url($url);
    }

    /**
     * A link that serves this file, or null when nothing can be served.
     *
     * A public image is linked directly. A private document goes through the
     * signed route so App\Http\Controllers\MediaController can authorize it
     * against the record it is attached to. An in-flight image has no bytes
     * written yet, and an orphaned document has nothing to authorize against,
     * so both return null and the action hides.
     */
    protected static function downloadUrl(Media $record): ?string
    {
        if ($record->isPubliclyReadable()) {
            return static::absoluteUrl($record);
        }

        // A file with no resolvable owner has nothing to authorize against, so
        // MediaController would refuse it anyway.
        return $record->hasResolvableOwner() ? $record->signedUrl() : null;
    }

    /**
     * The warning shown against a file nothing points at.
     *
     * Extracted rather than called inline because __() widens to string|array
     * once the translator is consulted, which fails a closure declaring
     * ?string at PHPStan level 8. See .ai/rules/i18n.md.
     */
    protected static function orphanWarning(): string
    {
        return (string) __('media.orphan_warning');
    }

    /**
     * A collection's name, translated at the point of display.
     *
     * MediaCollection is covered by a unit test and so must not call __()
     * itself -- see .ai/rules/i18n.md. A value with no case behind it (a row
     * written before a case was renamed) falls back to the stored string
     * rather than rendering a missing key.
     */
    protected static function collectionLabel(string $collection): string
    {
        if (MediaCollection::tryFrom($collection) === null) {
            return $collection;
        }

        $key = "media.collections.{$collection}";

        $label = __($key);

        return is_string($label) && $label !== $key ? $label : $collection;
    }

    /**
     * Describe the record a file is attached to.
     *
     * Built from the stored morph columns rather than by loading the record,
     * so a row whose owner is gone still says what it used to belong to
     * instead of failing. An unattached file says so in words -- it is about
     * to be collected by the prune, which is worth seeing.
     */
    protected static function describeOwner(Media $record): string
    {
        if ($record->model_type === null || $record->model_id === null) {
            return __('media.filters.orphaned');
        }

        $label = class_basename($record->model_type)." #{$record->model_id}";

        // A type whose class is gone still reads as what it used to be, marked
        // so it is obvious why the prune is about to collect it.
        return $record->hasResolvableOwner()
            ? $label
            : $label.' '.__('media.unknown_owner');
    }

    /**
     * Whether this file is ready, still processing, or simply stored.
     */
    protected static function statusLabel(Media $record): string
    {
        if ($record->conversion_set === null) {
            return __('media.status.stored');
        }

        return $record->isImage()
            ? __('media.status.ready')
            : __('media.status.processing');
    }

    /**
     * The badge colour matching statusLabel().
     */
    protected static function statusColour(Media $record): string
    {
        if ($record->conversion_set === null) {
            return 'gray';
        }

        return $record->isImage() ? 'success' : 'warning';
    }

    /**
     * The instant a chosen filter day begins, in the display timezone.
     *
     * @param  int  $addDays  Days to advance, for an exclusive upper bound.
     */
    protected static function displayDayStart(string $date, int $addDays = 0): Carbon
    {
        return Carbon::parse($date, app(Settings::class)->string(SettingKey::Timezone))
            ->startOfDay()
            ->addDays($addDays)
            ->utc();
    }
}
