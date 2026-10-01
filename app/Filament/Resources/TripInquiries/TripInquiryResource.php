<?php

namespace App\Filament\Resources\TripInquiries;

use App\Filament\Resources\TripInquiries\Pages\ListTripInquiries;
use App\Filament\Resources\TripInquiries\Pages\ViewTripInquiry;
use App\Models\TripInquiry;
use App\Settings\Settings;
use App\Travel\BookingWorkflow;
use App\Travel\InquiryStatus;
use App\Travel\NotEnoughSeats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The booking request inbox: every request from the public site, with the
 * quote the traveller saw, and the actions that move it to a booking.
 *
 * No create or edit form. The rows are a record of what travellers asked for;
 * the only change staff make is the status, through BookingWorkflow, which is
 * what holds and releases seats.
 */
class TripInquiryResource extends Resource
{
    protected static ?string $model = TripInquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('travel.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('travel.inquiries.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('travel.inquiries.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('travel.inquiries.navigation_label');
    }

    /**
     * The count of requests nobody has answered yet; no badge when clear.
     */
    public static function getNavigationBadge(): ?string
    {
        $new = TripInquiry::query()->where('status', InquiryStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('travel.inquiries.sections.trip'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('travel.inquiries.fields.status'))
                            ->formatStateUsing(fn (InquiryStatus $state): string => __($state->label()))
                            ->badge()
                            ->color(fn (InquiryStatus $state): string => $state->color()),
                        TextEntry::make('reference')
                            ->label(__('travel.inquiries.fields.reference'))
                            ->copyable()
                            ->fontFamily('mono'),
                        TextEntry::make('trip')
                            ->label(__('travel.inquiries.fields.trip'))
                            ->state(fn (TripInquiry $record): string => $record->tripLabel())
                            ->url(fn (TripInquiry $record): ?string => $record->tour !== null ? route('tours.show', $record->tour) : null)
                            ->openUrlInNewTab(),
                        TextEntry::make('departure')
                            ->label(fn (TripInquiry $record): string => $record->departure !== null
                                ? __('travel.inquiries.fields.departure')
                                : __('travel.inquiries.fields.travel_month'))
                            ->state(fn (TripInquiry $record): ?string => $record->departure?->dateRange() ?? $record->travel_month)
                            ->placeholder('—'),
                        TextEntry::make('party')
                            ->label(__('travel.inquiries.fields.party'))
                            ->state(fn (TripInquiry $record): string => __('travel.inquiries.fields.party_value', [
                                'adults' => $record->adults,
                                'children' => $record->children,
                            ])),
                        TextEntry::make('quoted_total_cents')
                            ->label(__('travel.inquiries.fields.quote'))
                            ->state(fn (TripInquiry $record): ?string => $record->formattedQuote())
                            ->placeholder('—'),
                        TextEntry::make('seats_left')
                            ->label(__('travel.inquiries.fields.seats_left'))
                            ->state(fn (TripInquiry $record): ?int => $record->departure?->seatsLeft())
                            ->visible(fn (TripInquiry $record): bool => $record->departure !== null),
                        TextEntry::make('confirmed_at')
                            ->label(__('travel.inquiries.fields.confirmed_at'))
                            ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state))
                            ->visible(fn (TripInquiry $record): bool => $record->confirmed_at !== null),
                    ]),
                Section::make(__('travel.inquiries.sections.traveller'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('travel.inquiries.fields.name')),
                        TextEntry::make('email')
                            ->label(__('travel.inquiries.fields.email'))
                            ->copyable(),
                        TextEntry::make('phone')
                            ->label(__('travel.inquiries.fields.phone'))
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label(__('travel.inquiries.fields.received'))
                            ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state)),
                        // The traveller's own words, escaped and never rendered.
                        TextEntry::make('message')
                            ->label(__('travel.inquiries.fields.message'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['tour', 'departure.tour', 'destination']))
            ->columns([
                TextColumn::make('status')
                    ->label(__('travel.inquiries.fields.status'))
                    ->formatStateUsing(fn (InquiryStatus $state): string => __($state->label()))
                    ->badge()
                    ->color(fn (InquiryStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('reference')
                    ->label(__('travel.inquiries.fields.reference'))
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('travel.inquiries.fields.name'))
                    ->description(fn (TripInquiry $record): string => $record->email)
                    ->searchable(['name', 'email']),
                TextColumn::make('trip')
                    ->label(__('travel.inquiries.fields.trip'))
                    ->state(fn (TripInquiry $record): string => $record->tripLabel())
                    ->description(fn (TripInquiry $record): ?string => $record->departure?->dateRange() ?? $record->travel_month)
                    ->wrap(),
                TextColumn::make('party')
                    ->label(__('travel.inquiries.fields.party'))
                    ->state(fn (TripInquiry $record): int => $record->travellers()),
                TextColumn::make('quoted_total_cents')
                    ->label(__('travel.inquiries.fields.quote'))
                    ->state(fn (TripInquiry $record): ?string => $record->formattedQuote())
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('travel.inquiries.fields.received'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('travel.inquiries.fields.status'))
                    ->options(InquiryStatus::options())
                    ->multiple(),
                TernaryFilter::make('custom')
                    ->label(__('travel.inquiries.fields.custom'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('tour_id'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('tour_id'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    /**
     * The status actions, shared by the view page header.
     *
     * @return list<Action>
     */
    public static function statusActions(): array
    {
        $workflow = app(BookingWorkflow::class);

        return [
            Action::make('contacted')
                ->label(__('travel.inquiries.actions.contacted'))
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('info')
                ->visible(fn (TripInquiry $record): bool => $record->status === InquiryStatus::New)
                ->authorize(fn (TripInquiry $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->action(fn (TripInquiry $record) => $workflow->markContacted($record)),
            Action::make('confirm')
                ->label(__('travel.inquiries.actions.confirm'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(fn (TripInquiry $record): ?string => self::confirmDescription($record))
                ->visible(fn (TripInquiry $record): bool => $record->status->isOpen())
                ->authorize(fn (TripInquiry $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->action(function (TripInquiry $record, Action $action) use ($workflow): void {
                    try {
                        $workflow->confirm($record);
                    } catch (NotEnoughSeats $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                        $action->halt();
                    }

                    Notification::make()->success()->title(__('travel.inquiries.actions.confirmed'))->send();
                }),
            Action::make('decline')
                ->label(__('travel.inquiries.actions.decline'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('travel.inquiries.actions.decline_description'))
                ->visible(fn (TripInquiry $record): bool => $record->status !== InquiryStatus::Declined)
                ->authorize(fn (TripInquiry $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->action(function (TripInquiry $record) use ($workflow): void {
                    $workflow->decline($record);
                    Notification::make()->title(__('travel.inquiries.actions.declined'))->send();
                }),
            Action::make('reply')
                ->label(__('travel.inquiries.actions.reply'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->url(fn (TripInquiry $record): string => 'mailto:'.$record->email.'?subject='.rawurlencode($record->reference.' — '.$record->tripLabel())),
        ];
    }

    /**
     * The confirm modal's warning that seats are about to be held.
     *
     * __() widens to string|array once replacements are passed; the array arm
     * is unreachable for this string key, so it is cast here once
     * (.ai/rules/i18n.md) rather than at the call site.
     */
    private static function confirmDescription(TripInquiry $record): ?string
    {
        if ($record->departure === null) {
            return null;
        }

        return (string) __('travel.inquiries.actions.confirm_description', ['count' => $record->travellers()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTripInquiries::route('/'),
            'view' => ViewTripInquiry::route('/{record}'),
        ];
    }
}
