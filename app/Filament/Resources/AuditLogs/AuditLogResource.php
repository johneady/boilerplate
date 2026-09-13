<?php

namespace App\Filament\Resources\AuditLogs;

use App\Audit\AuditEvent;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Settings\SettingKey;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * The read-only view of who changed what.
 *
 * There is no create, edit or delete action anywhere in this resource, and
 * that is not merely "not built yet": AuditLogPolicy denies those abilities to
 * everybody including administrators, and the Gate::before exemption in
 * AuthServiceProvider is what makes the denial reach an admin at all. A trail
 * an administrator can edit records only what they are willing to admit to.
 *
 * Entries leave through retention alone (app:prune-audit-log).
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('audit.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('audit.resource.plural_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('event')
                    ->label(__('audit.fields.event'))
                    ->badge()
                    ->formatStateUsing(fn (AuditEvent $state): string => $state->label())
                    ->color(fn (AuditEvent $state): string => $state->color()),
                TextEntry::make('created_at')
                    ->label(__('audit.fields.recorded'))
                    ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state)),
                TextEntry::make('user_name')
                    ->label(__('audit.fields.actor'))
                    // Read off the record rather than the relation: the foreign
                    // key goes null when the account is deleted, which is
                    // exactly when this entry is worth reading.
                    ->state(fn (AuditLog $record): string => $record->actorName()),
                TextEntry::make('user_email')
                    ->label(__('audit.fields.actor_email'))
                    ->placeholder('—'),
                TextEntry::make('auditable_type')
                    ->label(__('audit.fields.subject'))
                    ->state(fn (AuditLog $record): string => static::describeSubject($record))
                    ->placeholder('—'),
                TextEntry::make('ip_address')
                    ->label(__('audit.fields.ip_address'))
                    ->placeholder('—'),
                TextEntry::make('user_agent')
                    ->label(__('audit.fields.user_agent'))
                    ->placeholder('—')
                    ->columnSpanFull(),
                // Rendered as key/value rather than a diff widget: the values
                // are arbitrary attribute types, and a KeyValueEntry escapes
                // them. Some of this is user-submitted text.
                KeyValueEntry::make('old_values')
                    ->label(__('audit.fields.old_values'))
                    ->keyLabel(__('audit.fields.attribute'))
                    ->valueLabel(__('audit.fields.value'))
                    ->visible(fn (AuditLog $record): bool => ! empty($record->old_values))
                    ->columnSpanFull(),
                KeyValueEntry::make('new_values')
                    ->label(__('audit.fields.new_values'))
                    ->keyLabel(__('audit.fields.attribute'))
                    ->valueLabel(__('audit.fields.value'))
                    ->visible(fn (AuditLog $record): bool => ! empty($record->new_values))
                    ->columnSpanFull(),
                TextEntry::make('context')
                    ->label(__('audit.fields.context'))
                    ->state(fn (AuditLog $record): ?string => static::formatContext($record))
                    ->visible(fn (AuditLog $record): bool => $record->context !== null)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('audit.fields.recorded'))
                    ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state))
                    ->sortable(),
                TextColumn::make('event')
                    ->label(__('audit.fields.event'))
                    ->badge()
                    ->formatStateUsing(fn (AuditEvent $state): string => $state->label())
                    ->color(fn (AuditEvent $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('user_name')
                    ->label(__('audit.fields.actor'))
                    ->state(fn (AuditLog $record): string => $record->actorName())
                    // Searching the stored copies, not the relation: an entry
                    // whose account is gone must still be findable by name.
                    ->searchable(['user_name', 'user_email'])
                    ->description(fn (AuditLog $record): ?string => $record->user_email),
                TextColumn::make('auditable_type')
                    ->label(__('audit.fields.subject'))
                    ->state(fn (AuditLog $record): string => static::describeSubject($record))
                    ->placeholder('—'),
                TextColumn::make('ip_address')
                    ->label(__('audit.fields.ip_address'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Newest first: a trail is read from the most recent thing that
            // happened backwards.
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label(__('audit.fields.event'))
                    ->options(fn (): array => collect(AuditEvent::cases())
                        ->mapWithKeys(fn (AuditEvent $event): array => [$event->value => $event->label()])
                        ->all())
                    ->multiple(),
                SelectFilter::make('user_id')
                    ->label(__('audit.fields.actor'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('recorded_at')
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('audit.filters.from')),
                        DatePicker::make('until')
                            ->label(__('audit.filters.until')),
                    ])
                    // Half-open instant range rather than whereDate(). The
                    // column is stored in UTC but rendered in the display
                    // timezone, so comparing the raw date filed an entry under
                    // a different day than the one it is shown as: an entry at
                    // 02:00 UTC displays as the previous evening in Los
                    // Angeles, and filtering the day it showed excluded it.
                    // Resolving each bound in the display timezone and handing
                    // the query an instant makes the filter agree with the
                    // column, and keeps the created_at index usable.
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query
                                ->where('created_at', '>=', static::displayDayStart($date)),
                        )
                        ->when(
                            $data['until'] ?? null,
                            // The day AFTER the chosen one, exclusive, so the
                            // whole of "until" is included without depending on
                            // the column's sub-second precision.
                            fn (Builder $query, string $date): Builder => $query
                                ->where('created_at', '<', static::displayDayStart($date, 1)),
                        )),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            // No bulk actions and no record delete: see the class docblock.
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }

    /**
     * The instant a chosen filter day begins, in the display timezone.
     *
     * The date picker hands back a bare calendar day with no zone, and the
     * administrator picked it off a table rendered in the timezone setting --
     * so that is the zone it has to be resolved in. Converting to UTC afterwards
     * is what makes the comparison meet the stored column on its own terms.
     *
     * @param  int  $addDays  Days to advance, for building an exclusive upper bound.
     */
    protected static function displayDayStart(string $date, int $addDays = 0): Carbon
    {
        return Carbon::parse($date, app(Settings::class)->string(SettingKey::Timezone))
            ->startOfDay()
            ->addDays($addDays)
            ->utc();
    }

    /**
     * Render an entry's context as readable JSON.
     *
     * json_encode() returns false on a value it cannot encode -- malformed
     * UTF-8 in a recorded user agent or email would do it -- which would
     * otherwise reach the schema as a boolean where a string is expected.
     * Falling back to null shows the entry's placeholder instead.
     */
    protected static function formatContext(AuditLog $record): ?string
    {
        if ($record->context === null) {
            return null;
        }

        $encoded = json_encode($record->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded;
    }

    /**
     * Describe the record an entry refers to, for a column or an entry.
     *
     * Built from the stored class name and id rather than by loading the
     * record: a delete entry's subject no longer exists, and that is the entry
     * most worth reading. Returns an em dash for the events that have no
     * subject at all -- a sign-in is about the actor, not a record.
     */
    protected static function describeSubject(AuditLog $record): string
    {
        $label = $record->subjectLabel();

        if ($label === null) {
            return '—';
        }

        return $record->auditable_id === null
            ? $label
            : "{$label} #{$record->auditable_id}";
    }
}
