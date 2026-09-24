<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\RelationManagers\DisputesRelationManager;
use App\Filament\Resources\Payments\RelationManagers\RefundsRelationManager;
use App\Filament\Resources\Payments\RelationManagers\TransactionsRelationManager;
use App\Models\Payment;
use App\Payments\Enums\CaptureMethod;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\PaymentStatus;
use App\Payments\PaymentManager;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every payment taken, held, refunded or recorded by hand.
 *
 * Read-only apart from the money-moving actions (refund, capture, void), which
 * go through App\Payments\Actions rather than editing the row: a payment is a
 * financial record and nobody -- administrators included -- creates, edits or
 * deletes one here. See App\Policies\PaymentPolicy.
 *
 * Filtered to the installation's current mode by default, so sandbox payments
 * made while testing do not sit among real ones once the site goes live.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('payments.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('payments.payment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.payment.plural_label');
    }

    /**
     * Hidden, with the rest of the module, until payments are switched on.
     */
    public static function canAccess(): bool
    {
        return app(PaymentManager::class)->enabled() && parent::canAccess();
    }

    public static function infolist(Schema $schema): Schema
    {
        $settings = app(Settings::class);

        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('payments.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                            ->color(fn (PaymentStatus $state): string => $state->color()),
                        TextEntry::make('gateway')
                            ->label(__('payments.fields.gateway'))
                            ->badge()
                            ->formatStateUsing(fn (Gateway $state): string => $state->label())
                            ->color(fn (Gateway $state): string => $state->color()),
                        TextEntry::make('mode')
                            ->label(__('payments.fields.mode'))
                            ->badge()
                            ->formatStateUsing(fn (GatewayMode $state): string => $state->label())
                            ->color(fn (GatewayMode $state): string => $state->color()),
                        TextEntry::make('description')
                            ->label(__('payments.fields.description')),
                        TextEntry::make('customer_name')
                            ->label(__('payments.fields.customer_name')),
                        TextEntry::make('customer_email')
                            ->label(__('payments.fields.customer_email'))
                            ->copyable(),
                        TextEntry::make('subtotal')
                            ->label(__('payments.fields.subtotal'))
                            ->state(fn (Payment $record): string => $record->subtotalMoney()->format()),
                        TextEntry::make('tax_total')
                            ->label(__('payments.fields.tax'))
                            ->state(fn (Payment $record): string => $record->tax_lines === []
                                ? '—'
                                : collect($record->taxLines())->map(fn ($line): string => $line->label().': '.$line->amount->format())->implode(', ')),
                        TextEntry::make('amount')
                            ->label(__('payments.fields.total'))
                            ->state(fn (Payment $record): string => $record->total()->format())
                            ->weight('bold'),
                        TextEntry::make('amount_captured')
                            ->label(__('payments.fields.captured'))
                            ->state(fn (Payment $record): string => $record->capturedMoney()->format()),
                        TextEntry::make('amount_refunded')
                            ->label(__('payments.fields.refunded'))
                            ->state(fn (Payment $record): string => $record->refundedMoney()->format()),
                        TextEntry::make('capture_method')
                            ->label(__('payments.fields.capture_method'))
                            ->formatStateUsing(fn (CaptureMethod $state): string => $state === CaptureMethod::Manual
                                ? __('payments.fields.capture_manual')
                                : __('payments.fields.capture_automatic')),
                        TextEntry::make('authorization_expires_at')
                            ->label(__('payments.fields.authorization_expires'))
                            ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state))
                            ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::Authorized),
                        TextEntry::make('paid_at')
                            ->label(__('payments.fields.paid_at'))
                            ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state))
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label(__('payments.fields.created'))
                            ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state)),
                        TextEntry::make('failure_reason')
                            ->label(__('payments.fields.failure_reason'))
                            ->visible(fn (Payment $record): bool => filled($record->failure_reason))
                            ->columnSpanFull(),
                        TextEntry::make('manual_method')
                            ->label(__('payments.fields.manual_method'))
                            ->formatStateUsing(fn ($state): string => $state?->label() ?? '—')
                            ->visible(fn (Payment $record): bool => $record->gateway === Gateway::Manual),
                        TextEntry::make('manual_reference')
                            ->label(__('payments.fields.manual_reference'))
                            ->placeholder('—')
                            ->visible(fn (Payment $record): bool => $record->gateway === Gateway::Manual),
                        TextEntry::make('manual_received_on')
                            ->label(__('payments.fields.manual_received_on'))
                            ->formatStateUsing(fn ($state): string => $settings->formatDate($state))
                            ->visible(fn (Payment $record): bool => $record->gateway === Gateway::Manual),
                        TextEntry::make('uuid')
                            ->label(__('payments.fields.reference'))
                            ->fontFamily('mono')
                            ->copyable(),
                        TextEntry::make('gateway_checkout_id')
                            ->label(__('payments.fields.gateway_checkout_id'))
                            ->fontFamily('mono')
                            ->placeholder('—'),
                        TextEntry::make('gateway_payment_id')
                            ->label(__('payments.fields.gateway_payment_id'))
                            ->fontFamily('mono')
                            ->placeholder('—'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $settings = app(Settings::class);

        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('payments.fields.created'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state))
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label(__('payments.fields.customer'))
                    ->description(fn (Payment $record): string => $record->customer_email)
                    ->searchable(['customer_name', 'customer_email', 'uuid']),
                TextColumn::make('description')
                    ->label(__('payments.fields.description'))
                    ->limit(40),
                TextColumn::make('amount')
                    ->label(__('payments.fields.total'))
                    ->state(fn (Payment $record): string => $record->total()->format())
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('payments.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color()),
                TextColumn::make('gateway')
                    ->label(__('payments.fields.gateway'))
                    ->badge()
                    ->formatStateUsing(fn (Gateway $state): string => $state->label())
                    ->color(fn (Gateway $state): string => $state->color()),
                TextColumn::make('mode')
                    ->label(__('payments.fields.mode'))
                    ->badge()
                    ->formatStateUsing(fn (GatewayMode $state): string => $state->label())
                    ->color(fn (GatewayMode $state): string => $state->color())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('payments.fields.status'))
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $status): array => [$status->value => $status->label()])->all())
                    ->multiple(),
                SelectFilter::make('gateway')
                    ->label(__('payments.fields.gateway'))
                    ->options(collect(Gateway::cases())->mapWithKeys(fn (Gateway $gateway): array => [$gateway->value => $gateway->label()])->all()),
                SelectFilter::make('mode')
                    ->label(__('payments.fields.mode'))
                    ->options(collect(GatewayMode::cases())->mapWithKeys(fn (GatewayMode $mode): array => [$mode->value => $mode->label()])->all())
                    ->default(fn (): string => app(PaymentManager::class)->mode()->value),
            ])
            ->recordActions([
                ViewAction::make(),
                PaymentActions::refund(),
                PaymentActions::capture(),
                PaymentActions::void(),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
            RefundsRelationManager::class,
            DisputesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
