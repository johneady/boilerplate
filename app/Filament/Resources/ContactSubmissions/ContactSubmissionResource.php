<?php

namespace App\Filament\Resources\ContactSubmissions;

use App\Filament\Resources\ContactSubmissions\Pages\ManageContactSubmissions;
use App\Models\ContactSubmission;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The inbox for messages sent through the public contact form.
 *
 * Read, mark handled, delete. There is deliberately no create or edit form: the
 * rows are a record of what members of the public actually sent, and an editable
 * record of somebody else's words is worth less than none.
 */
class ContactSubmissionResource extends Resource
{
    protected static ?string $model = ContactSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('pages.submissions.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pages.submissions.plural_label');
    }

    /**
     * The sidebar line. Shorter than the plural label, which stays on the
     * screen itself: the menu already sits in the Content group, where the
     * "contact" half of the name adds no information.
     */
    public static function getNavigationLabel(): string
    {
        return __('pages.submissions.navigation_label');
    }

    /**
     * The count of submissions nobody has dealt with yet.
     *
     * Returned as null rather than "0" when the inbox is clear, so the sidebar
     * shows no badge at all instead of a zero that reads like a notification.
     */
    public static function getNavigationBadge(): ?string
    {
        // Queried through the model class rather than static::getModel(), whose
        // class-string return loses the generic type the unhandled() scope is
        // declared on.
        $unhandled = ContactSubmission::query()->unhandled()->count();

        return $unhandled > 0 ? (string) $unhandled : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('pages.submissions.fields.name')),
                TextEntry::make('email')
                    ->label(__('pages.submissions.fields.email'))
                    ->copyable(),
                TextEntry::make('subject')
                    ->label(__('pages.submissions.fields.subject'))
                    ->placeholder(__('pages.submissions.fields.no_subject')),
                TextEntry::make('created_at')
                    ->label(__('pages.submissions.fields.received'))
                    ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state)),
                // The message is the visitor's own text. TextEntry escapes it,
                // and it is NOT passed through any Markdown or HTML rendering:
                // this is an untrusted stranger's input shown to an
                // administrator, so it stays literal text.
                TextEntry::make('message')
                    ->label(__('pages.submissions.fields.message'))
                    ->columnSpanFull(),
                TextEntry::make('ip_address')
                    ->label(__('pages.submissions.fields.ip_address'))
                    ->placeholder('—'),
                TextEntry::make('user_agent')
                    ->label(__('pages.submissions.fields.user_agent'))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('handled_at')
                    ->label(__('pages.submissions.fields.handled'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('pages.submissions.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('pages.submissions.fields.email'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('subject')
                    ->label(__('pages.submissions.fields.subject'))
                    ->searchable()
                    ->limit(40)
                    ->placeholder(__('pages.submissions.fields.no_subject')),
                TextColumn::make('created_at')
                    ->label(__('pages.submissions.fields.received'))
                    ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state))
                    ->sortable(),
            ])
            // Newest first: an inbox is read from the top.
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('handled_at')
                    ->label(__('pages.submissions.fields.handled'))
                    ->nullable(),
            ])
            ->recordActions([
                ViewAction::make(),
                // One action that reads as its own inverse, rather than two
                // that are each wrong half the time.
                Action::make('toggleHandled')
                    ->label(fn (ContactSubmission $record): string => $record->isHandled()
                        ? __('pages.submissions.actions.mark_unhandled')
                        : __('pages.submissions.actions.mark_handled'))
                    ->icon(fn (ContactSubmission $record): Heroicon => $record->isHandled()
                        ? Heroicon::OutlinedArrowUturnLeft
                        : Heroicon::OutlinedCheck)
                    ->authorize(fn (ContactSubmission $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(fn (ContactSubmission $record) => $record
                        ->forceFill(['handled_at' => $record->isHandled() ? null : now()])
                        ->save()),
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
            'index' => ManageContactSubmissions::route('/'),
        ];
    }
}
