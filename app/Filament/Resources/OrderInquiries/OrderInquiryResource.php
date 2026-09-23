<?php

namespace App\Filament\Resources\OrderInquiries;

use App\Bakery\Fulfilment;
use App\Bakery\InquiryStatus;
use App\Bakery\Money;
use App\Bakery\Occasion;
use App\Filament\Resources\OrderInquiries\Pages\ListOrderInquiries;
use App\Filament\Resources\OrderInquiries\Pages\ViewOrderInquiry;
use App\Models\OrderInquiry;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Order inquiries from the public order form -- the baker's order book.
 *
 * Worked from the list: the status is changed inline, the tabs split what
 * needs a reply from what is on the schedule, and the view page carries the
 * quote and private notes. No create or edit form: what the customer asked
 * for is a record, not something to rewrite.
 */
class OrderInquiryResource extends Resource
{
    protected static ?string $model = OrderInquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Bakery';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getModelLabel(): string
    {
        return __('bakery.inquiries.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('bakery.inquiries.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('bakery.inquiries.navigation_label');
    }

    /**
     * The count of inquiries nobody has replied to, or no badge at all.
     */
    public static function getNavigationBadge(): ?string
    {
        $new = OrderInquiry::query()->awaitingReply()->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('bakery.inquiries.new_badge');
    }

    /**
     * The status choices, labelled for the panel.
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect(InquiryStatus::cases())
            ->mapWithKeys(fn (InquiryStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    public static function infolist(Schema $schema): Schema
    {
        $settings = app(Settings::class);

        return $schema
            ->columns(3)
            ->components([
                Section::make(__('bakery.inquiries.sections.order'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('needed_on')
                            ->label(__('bakery.inquiries.fields.needed_on'))
                            ->formatStateUsing(fn ($state): string => $settings->formatDate($state))
                            ->weight('bold'),
                        TextEntry::make('fulfilment')
                            ->label(__('bakery.inquiries.fields.fulfilment'))
                            ->formatStateUsing(fn (Fulfilment $state): string => __($state->label()))
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('delivery_address')
                            ->label(__('bakery.inquiries.fields.delivery_address'))
                            ->visible(fn (OrderInquiry $record): bool => filled($record->delivery_address))
                            ->columnSpanFull(),
                        TextEntry::make('occasion')
                            ->label(__('bakery.inquiries.fields.occasion'))
                            ->formatStateUsing(fn (?Occasion $state): string => $state === null ? '—' : __($state->label()))
                            ->placeholder('—'),
                        TextEntry::make('estimated_total_cents')
                            ->label(__('bakery.inquiries.fields.estimate'))
                            ->formatStateUsing(fn (int $state): string => Money::format($state)),
                        RepeatableEntry::make('items')
                            ->label(__('bakery.inquiries.fields.items'))
                            ->table([
                                TableColumn::make(__('bakery.inquiries.fields.item')),
                                TableColumn::make(__('bakery.inquiries.fields.quantity')),
                                TableColumn::make(__('bakery.inquiries.fields.unit_price')),
                            ])
                            ->schema([
                                TextEntry::make('name'),
                                TextEntry::make('quantity'),
                                TextEntry::make('price_cents')
                                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                            ])
                            ->columnSpanFull(),
                        // The customer's own words: escaped by TextEntry and
                        // never run through Markdown -- a stranger wrote them.
                        TextEntry::make('details')
                            ->label(__('bakery.inquiries.fields.details'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('allergies')
                            ->label(__('bakery.inquiries.fields.allergies'))
                            ->placeholder('—')
                            ->color('danger')
                            ->columnSpanFull(),
                    ]),
                // The customer and the baker's side share the right-hand
                // column, beside the order itself.
                Group::make([
                    Section::make(__('bakery.inquiries.sections.customer'))
                        ->schema([
                            TextEntry::make('name')->label(__('bakery.inquiries.fields.name')),
                            TextEntry::make('email')->label(__('bakery.inquiries.fields.email'))->copyable(),
                            TextEntry::make('phone')->label(__('bakery.inquiries.fields.phone'))->placeholder('—')->copyable(),
                            TextEntry::make('created_at')
                                ->label(__('bakery.inquiries.fields.received'))
                                ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state)),
                        ]),
                    Section::make(__('bakery.inquiries.sections.baker'))
                        ->schema([
                            TextEntry::make('status')
                                ->label(__('bakery.inquiries.fields.status'))
                                ->formatStateUsing(fn (InquiryStatus $state): string => $state->label())
                                ->badge()
                                ->color(fn (InquiryStatus $state): string => $state->color()),
                            TextEntry::make('quoted_total_cents')
                                ->label(__('bakery.inquiries.fields.quote'))
                                ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Money::format($state))
                                ->placeholder('—'),
                            TextEntry::make('baker_notes')
                                ->label(__('bakery.inquiries.fields.baker_notes'))
                                ->placeholder('—'),
                        ]),
                ])->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        $settings = app(Settings::class);

        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label(__('bakery.inquiries.fields.reference'))
                    ->fontFamily('mono')
                    ->searchable()
                    ->description(fn (OrderInquiry $record): string => $settings->formatRelative($record->created_at)),
                TextColumn::make('needed_on')
                    ->label(__('bakery.inquiries.fields.needed_on'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDate($state))
                    ->description(fn (OrderInquiry $record): string => __($record->fulfilment->label()))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('bakery.inquiries.fields.customer'))
                    ->description(fn (OrderInquiry $record): string => $record->email)
                    ->searchable(['name', 'email', 'phone']),
                TextColumn::make('items')
                    ->label(__('bakery.inquiries.fields.items'))
                    ->state(fn (OrderInquiry $record): string => $record->itemSummary())
                    ->wrap()
                    ->lineClamp(2),
                TextColumn::make('estimated_total_cents')
                    ->label(__('bakery.inquiries.fields.estimate'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->description(fn (OrderInquiry $record): ?string => static::quoteLine($record))
                    ->toggleable(),
                // Changed inline, because working the list is the job. Inline
                // columns skip policies, so the update check is made here.
                SelectColumn::make('status')
                    ->label(__('bakery.inquiries.fields.status'))
                    ->options(static::statusOptions())
                    ->selectablePlaceholder(false)
                    ->disabled(fn (OrderInquiry $record): bool => ! (auth()->user()?->can('update', $record) ?? false))
                    ->width('12rem'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('bakery.inquiries.fields.status'))
                    ->options(static::statusOptions())
                    ->multiple(),
                SelectFilter::make('fulfilment')
                    ->label(__('bakery.inquiries.fields.fulfilment'))
                    ->options(collect(Fulfilment::cases())->mapWithKeys(fn (Fulfilment $option): array => [$option->value => __($option->label())])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * The "Quoted: $98.00" line under the estimate, or null before a quote.
     *
     * Cast rather than returned as-is: with replacements __() is typed
     * string|array, and the array arm is unreachable for a string key (see
     * .ai/rules/i18n.md).
     */
    protected static function quoteLine(OrderInquiry $record): ?string
    {
        $quote = $record->formattedQuote();

        return $quote === null ? null : (string) __('bakery.inquiries.quoted', ['price' => $quote]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrderInquiries::route('/'),
            'view' => ViewOrderInquiry::route('/{record}'),
        ];
    }
}
