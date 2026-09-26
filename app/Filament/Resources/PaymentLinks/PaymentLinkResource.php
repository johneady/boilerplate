<?php

namespace App\Filament\Resources\PaymentLinks;

use App\Filament\Actions\RecordManualPaymentAction;
use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\PaymentLinks\Pages\ManagePaymentLinks;
use App\Models\PaymentLink;
use App\Payments\Enums\PaymentLinkAmountType;
use App\Payments\Enums\PaymentLinkUsage;
use App\Payments\PaymentManager;
use App\Settings\Settings;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Shareable /pay/{token} links: invoices, deposits, donations, fixed-price
 * services.
 *
 * A link that has taken a payment cannot be deleted (its payments name it as
 * what they paid for) -- switch it off instead. There is no bulk delete for
 * the same reason: the per-record rule would have to be re-checked for every
 * selected row.
 */
class PaymentLinkResource extends Resource
{
    protected static ?string $model = PaymentLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('payments.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('payments.link.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.link.plural_label');
    }

    public static function canAccess(): bool
    {
        return app(PaymentManager::class)->enabled() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        $record = $schema->getRecord();
        $currency = $record instanceof PaymentLink ? $record->currency : app(PaymentManager::class)->currency();
        $isCustomerEntered = fn (Get $get): bool => $get('amount_type') === PaymentLinkAmountType::CustomerEntered->value
            || $get('amount_type') === PaymentLinkAmountType::CustomerEntered;

        return $schema
            ->components([
                TextInput::make('title')
                    ->label(__('payments.links.title'))
                    ->helperText(__('payments.links.title_help'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label(__('payments.links.description'))
                    ->helperText(__('payments.links.description_help'))
                    ->maxLength(2000)
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('amount_type')
                    ->label(__('payments.links.amount_type'))
                    ->options(collect(PaymentLinkAmountType::cases())->mapWithKeys(fn (PaymentLinkAmountType $type): array => [$type->value => __($type->label())])->all())
                    ->default(PaymentLinkAmountType::Fixed->value)
                    ->required()
                    ->selectablePlaceholder(false)
                    ->live(),
                MoneyInput::make('amount', $currency)
                    ->label(__('payments.links.amount'))
                    ->required(fn (Get $get): bool => ! $isCustomerEntered($get))
                    ->positive()
                    ->visible(fn (Get $get): bool => ! $isCustomerEntered($get)),
                MoneyInput::make('min_amount', $currency)
                    ->label(__('payments.links.min_amount'))
                    ->positive()
                    ->visible($isCustomerEntered),
                MoneyInput::make('max_amount', $currency)
                    ->label(__('payments.links.max_amount'))
                    ->positive()
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $minimum = $get('min_amount');

                        if (filled($value) && is_numeric($minimum) && is_numeric($value) && (float) $value < (float) $minimum) {
                            $fail(__('payments.links.max_below_min'));
                        }
                    })
                    ->visible($isCustomerEntered),
                Select::make('usage')
                    ->label(__('payments.links.usage'))
                    ->options(collect(PaymentLinkUsage::cases())->mapWithKeys(fn (PaymentLinkUsage $usage): array => [$usage->value => __($usage->label())])->all())
                    ->default(PaymentLinkUsage::SingleUse->value)
                    ->required()
                    ->selectablePlaceholder(false),
                DateTimePicker::make('expires_at')
                    ->label(__('payments.links.expires_at'))
                    ->helperText(__('payments.links.expires_at_help')),
                Toggle::make('taxable')
                    ->label(__('payments.links.taxable'))
                    ->helperText(__('payments.links.taxable_help'))
                    ->default(fn (Get $get): bool => ! $isCustomerEntered($get)),
                Toggle::make('is_active')
                    ->label(__('payments.links.is_active'))
                    ->helperText(__('payments.links.is_active_help'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        $settings = app(Settings::class);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('payments'))
            ->columns([
                TextColumn::make('title')
                    ->label(__('payments.links.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('payments.links.amount_type'))
                    ->state(fn (PaymentLink $record): string => $record->fixedAmount()?->format()
                        ?? __('payments.links.customer_entered')),
                TextColumn::make('usage')
                    ->label(__('payments.links.usage'))
                    ->formatStateUsing(fn (PaymentLinkUsage $state): string => __($state->label()))
                    ->description(fn (PaymentLink $record): ?string => $record->settled_payment_id !== null ? static::settledLabel() : null),
                IconColumn::make('is_active')
                    ->label(__('payments.links.is_active'))
                    ->boolean(),
                TextColumn::make('expires_at')
                    ->label(__('payments.links.expires_at'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('payments_count')
                    ->label(__('payments.links.payments_count')),
                TextColumn::make('token')
                    ->label(__('payments.links.url'))
                    ->state(fn (PaymentLink $record): string => $record->url())
                    ->copyable()
                    ->copyMessage(__('payments.links.copied'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('open')
                    ->label(__('payments.links.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (PaymentLink $record): string => $record->url())
                    ->openUrlInNewTab(),
                RecordManualPaymentAction::make(),
                EditAction::make(),
                // Shown, disabled, on a link that has taken payments, rather
                // than silently hidden (PaymentLinkPolicy::delete): the
                // administrator learns why instead of wondering where the
                // button went. Same treatment as a synced tax rate.
                DeleteAction::make()
                    ->authorizationTooltip(fn (PaymentLink $record): bool => $record->payments_count > 0)
                    ->authorizationMessage(__('payments.links.delete_taken')),
            ])
            ->toolbarActions([]);
    }

    /**
     * The note under a single-use link that has been paid.
     *
     * A method rather than __() inline because the translator is typed
     * string|array (a key may name a group of lines); this key never does, and
     * the column's closure declares ?string. See .ai/rules/i18n.md.
     */
    protected static function settledLabel(): string
    {
        return (string) __('payments.links.settled');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentLinks::route('/'),
        ];
    }
}
