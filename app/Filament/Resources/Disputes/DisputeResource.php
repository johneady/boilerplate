<?php

namespace App\Filament\Resources\Disputes;

use App\Filament\Resources\Disputes\Pages\ListDisputes;
use App\Filament\Resources\Disputes\Pages\ViewDispute;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Dispute;
use App\Payments\Enums\DisputeStatus;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\PaymentManager;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Chargebacks and PayPal claims, mirrored from the gateways.
 *
 * Read-only: evidence is submitted in the gateway's dashboard, linked from
 * each dispute. The navigation badge counts disputes waiting on a response in
 * the current mode, since each has a deadline after which it is lost.
 */
class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?int $navigationSort = 12;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('payments.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('payments.dispute.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.dispute.plural_label');
    }

    public static function canAccess(): bool
    {
        return app(PaymentManager::class)->enabled() && parent::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $open = Dispute::query()->needingResponse(app(PaymentManager::class)->mode())->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
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
                            ->formatStateUsing(fn (DisputeStatus $state): string => __($state->label()))
                            ->color(fn (DisputeStatus $state): string => $state->color()),
                        TextEntry::make('amount')
                            ->label(__('payments.fields.amount'))
                            ->state(fn (Dispute $record): string => $record->money()->format()),
                        TextEntry::make('reason')
                            ->label(__('payments.fields.reason'))
                            ->placeholder('—'),
                        TextEntry::make('evidence_due_by')
                            ->label(__('payments.disputes.evidence_due_by'))
                            ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state))
                            ->placeholder('—'),
                        TextEntry::make('gateway')
                            ->label(__('payments.fields.gateway'))
                            ->badge()
                            ->formatStateUsing(fn (Gateway $state): string => __($state->label()))
                            ->color(fn (Gateway $state): string => $state->color()),
                        TextEntry::make('mode')
                            ->label(__('payments.fields.mode'))
                            ->badge()
                            ->formatStateUsing(fn (GatewayMode $state): string => __($state->label()))
                            ->color(fn (GatewayMode $state): string => $state->color()),
                        TextEntry::make('payment.description')
                            ->label(__('payments.disputes.payment'))
                            ->url(fn (Dispute $record): ?string => $record->payment !== null ? PaymentResource::getUrl('view', ['record' => $record->payment]) : null),
                        TextEntry::make('payment.customer_name')
                            ->label(__('payments.fields.customer')),
                        TextEntry::make('created_at')
                            ->label(__('payments.disputes.opened'))
                            ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state)),
                        TextEntry::make('gateway_dispute_id')
                            ->label(__('payments.disputes.gateway_dispute_id'))
                            ->fontFamily('mono')
                            ->copyable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $settings = app(Settings::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('payment'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('payments.disputes.opened'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state))
                    ->sortable(),
                TextColumn::make('payment.customer_name')
                    ->label(__('payments.fields.customer'))
                    ->description(fn (Dispute $record): string => $record->payment->description ?? ''),
                TextColumn::make('amount')
                    ->label(__('payments.fields.amount'))
                    ->state(fn (Dispute $record): string => $record->money()->format()),
                TextColumn::make('status')
                    ->label(__('payments.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (DisputeStatus $state): string => __($state->label()))
                    ->color(fn (DisputeStatus $state): string => $state->color()),
                TextColumn::make('evidence_due_by')
                    ->label(__('payments.disputes.evidence_due_by'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('gateway')
                    ->label(__('payments.fields.gateway'))
                    ->badge()
                    ->formatStateUsing(fn (Gateway $state): string => __($state->label()))
                    ->color(fn (Gateway $state): string => $state->color()),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('payments.fields.status'))
                    ->options(collect(DisputeStatus::cases())->mapWithKeys(fn (DisputeStatus $status): array => [$status->value => __($status->label())])->all())
                    ->multiple(),
                SelectFilter::make('mode')
                    ->label(__('payments.fields.mode'))
                    ->options(collect(GatewayMode::cases())->mapWithKeys(fn (GatewayMode $mode): array => [$mode->value => __($mode->label())])->all())
                    ->default(fn (): string => app(PaymentManager::class)->mode()->value),
            ])
            ->recordActions([
                ViewAction::make(),
                static::respondAction(),
            ])
            ->toolbarActions([]);
    }

    /**
     * Open the dispute in the gateway's dashboard, where evidence is submitted.
     */
    public static function respondAction(): Action
    {
        return Action::make('respond')
            ->label(__('payments.disputes.respond'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->url(fn (Dispute $record): string => $record->dashboardUrl(), shouldOpenInNewTab: true);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDisputes::route('/'),
            'view' => ViewDispute::route('/{record}'),
        ];
    }
}
