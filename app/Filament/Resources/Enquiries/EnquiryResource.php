<?php

namespace App\Filament\Resources\Enquiries;

use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Enquiries\Pages\ViewEnquiry;
use App\Models\Enquiry;
use App\Models\Vehicle;
use App\Settings\Settings;
use App\Voltiva\DrivingNeed;
use App\Voltiva\EnquiryStatus;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Customer enquiries -- the built-in CRM.
 *
 * Records what the brief asks for (customer, car, date, source, needs,
 * finance and registration interest) and the sales team works it from
 * here: the status is changed inline, notes are kept on the record, and the
 * automatic email sequence is visible per enquiry. No create or edit form:
 * the customer's own words are a record, not something to rewrite.
 */
class EnquiryResource extends Resource
{
    protected static ?string $model = Enquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('voltiva.enquiries.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('voltiva.enquiries.plural_label');
    }

    /**
     * The count of enquiries nobody has contacted yet, or no badge at all.
     */
    public static function getNavigationBadge(): ?string
    {
        $new = Enquiry::query()->where('status', EnquiryStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect(EnquiryStatus::cases())
            ->mapWithKeys(fn (EnquiryStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    public static function infolist(Schema $schema): Schema
    {
        $settings = app(Settings::class);

        return $schema
            ->columns(3)
            ->components([
                Section::make(__('voltiva.enquiries.sections.customer'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label(__('voltiva.enquiries.fields.name')),
                        TextEntry::make('email')->label(__('voltiva.enquiries.fields.email'))->copyable(),
                        TextEntry::make('phone')->label(__('voltiva.enquiries.fields.phone'))->placeholder('—')->copyable(),
                        TextEntry::make('location')->label(__('voltiva.enquiries.fields.location'))->placeholder('—'),
                        TextEntry::make('vehicle_name')->label(__('voltiva.enquiries.fields.vehicle'))->placeholder(__('voltiva.enquiries.not_sure')),
                        TextEntry::make('driving_needs')
                            ->label(__('voltiva.enquiries.fields.driving_needs'))
                            ->state(fn (Enquiry $record): array => array_map(fn (DrivingNeed $need): string => $need->label(), $record->drivingNeeds()))
                            ->badge()
                            ->color('gray')
                            ->placeholder('—'),
                        IconEntry::make('finance_interest')->label(__('voltiva.enquiries.fields.finance_interest'))->boolean(),
                        IconEntry::make('registration_interest')->label(__('voltiva.enquiries.fields.registration_interest'))->boolean(),
                        // The customer's own words: escaped by TextEntry and
                        // never run through Markdown -- a stranger wrote them.
                        TextEntry::make('message')->label(__('voltiva.enquiries.fields.message'))->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make(__('voltiva.enquiries.sections.sales'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('voltiva.enquiries.fields.status'))
                            ->formatStateUsing(fn (EnquiryStatus $state): string => $state->label())
                            ->badge()
                            ->color(fn (EnquiryStatus $state): string => $state->color()),
                        TextEntry::make('created_at')
                            ->label(__('voltiva.enquiries.fields.received'))
                            ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state)),
                        TextEntry::make('source')
                            ->label(__('voltiva.enquiries.fields.source'))
                            ->formatStateUsing(fn (Enquiry $record): string => $record->sourceLabel()),
                        TextEntry::make('locale')
                            ->label(__('voltiva.enquiries.fields.language'))
                            ->formatStateUsing(fn (string $state): string => (string) (config('voltiva.locales')[$state] ?? $state)),
                        TextEntry::make('staff_notes')
                            ->label(__('voltiva.enquiries.fields.staff_notes'))
                            ->placeholder(__('voltiva.enquiries.no_notes')),
                    ]),
                Section::make(__('voltiva.enquiries.sections.emails'))
                    ->description(__('voltiva.enquiries.sections.emails_help'))
                    ->columnSpanFull()
                    ->schema([
                        ViewEntry::make('timeline')
                            ->hiddenLabel()
                            ->view('filament.enquiries.follow-up-timeline'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $settings = app(Settings::class);

        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('voltiva.enquiries.fields.received'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('voltiva.enquiries.fields.customer'))
                    ->description(fn (Enquiry $record): string => $record->email)
                    ->searchable(['name', 'email', 'phone']),
                TextColumn::make('vehicle_name')
                    ->label(__('voltiva.enquiries.fields.vehicle'))
                    ->placeholder(__('voltiva.enquiries.not_sure')),
                TextColumn::make('location')
                    ->label(__('voltiva.enquiries.fields.location'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('source')
                    ->label(__('voltiva.enquiries.fields.source'))
                    ->formatStateUsing(fn (Enquiry $record): string => $record->sourceLabel())
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                IconColumn::make('finance_interest')
                    ->label(__('voltiva.enquiries.fields.finance'))
                    ->boolean(),
                IconColumn::make('registration_interest')
                    ->label(__('voltiva.enquiries.fields.registration'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Changed inline, because working the list is the job. Inline
                // columns skip policies, so the update check is made here.
                SelectColumn::make('status')
                    ->label(__('voltiva.enquiries.fields.status'))
                    ->options(static::statusOptions())
                    ->selectablePlaceholder(false)
                    ->disabled(fn (Enquiry $record): bool => ! (auth()->user()?->can('update', $record) ?? false))
                    ->width('13rem'),
                TextColumn::make('follow_up_step')
                    ->label(__('voltiva.enquiries.fields.emails_sent'))
                    ->formatStateUsing(fn (int $state): string => $state.' / '.Enquiry::followUpCount())
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('voltiva.enquiries.fields.status'))
                    ->options(static::statusOptions())
                    ->multiple(),
                SelectFilter::make('vehicle_id')
                    ->label(__('voltiva.enquiries.fields.vehicle'))
                    ->options(fn (): array => Vehicle::query()->ordered()->pluck('name', 'id')->all()),
                SelectFilter::make('source')
                    ->label(__('voltiva.enquiries.fields.source'))
                    ->options(Enquiry::SOURCES),
                TernaryFilter::make('finance_interest')
                    ->label(__('voltiva.enquiries.fields.finance_interest')),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnquiries::route('/'),
            'view' => ViewEnquiry::route('/{record}'),
        ];
    }
}
