<?php

namespace App\Filament\Resources\Subscriptions;

use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Filament\Resources\Subscriptions\RelationManagers\PaymentsRelationManager;
use App\Models\Subscription;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\PaymentManager;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
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
 * Every customer subscription, mirrored from the gateway that bills it.
 *
 * Read-only apart from the cancel, resume and Demo simulation actions, which
 * go through App\Payments\Actions and the gateway rather than editing the
 * row. Filtered to the current mode by default, like payments.
 */
class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?int $navigationSort = 15;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('payments.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('payments.subscription.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.subscription.plural_label');
    }

    public static function canAccess(): bool
    {
        return app(PaymentManager::class)->enabled() && parent::canAccess();
    }

    public static function infolist(Schema $schema): Schema
    {
        $settings = app(Settings::class);
        $date = fn ($state): string => $settings->formatDateTime($state);

        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('payments.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn (SubscriptionStatus $state): string => __($state->label()))
                            ->color(fn (SubscriptionStatus $state): string => $state->color()),
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
                        TextEntry::make('user.name')
                            ->label(__('payments.fields.customer'))
                            ->placeholder(__('payments.subscriptions.deleted_user')),
                        TextEntry::make('user.email')
                            ->label(__('payments.fields.customer_email'))
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('plan.name')
                            ->label(__('payments.subscriptions.plan')),
                        TextEntry::make('price')
                            ->label(__('payments.plans.price'))
                            ->state(fn (Subscription $record): string => $record->price?->label() ?? '—'),
                        TextEntry::make('pendingPrice')
                            ->label(__('payments.subscriptions.pending_price'))
                            ->state(fn (Subscription $record): ?string => $record->pendingPrice?->label())
                            ->visible(fn (Subscription $record): bool => $record->pending_plan_price_id !== null),
                        TextEntry::make('trial_ends_at')
                            ->label(__('payments.subscriptions.trial_ends'))
                            ->formatStateUsing($date)
                            ->placeholder('—'),
                        TextEntry::make('current_period_end')
                            ->label(__('payments.subscriptions.period_ends'))
                            ->formatStateUsing($date)
                            ->placeholder('—'),
                        IconEntry::make('cancel_at_period_end')
                            ->label(__('payments.subscriptions.cancel_at_period_end'))
                            ->boolean(),
                        TextEntry::make('ends_at')
                            ->label(__('payments.subscriptions.ends'))
                            ->formatStateUsing($date)
                            ->placeholder('—'),
                        TextEntry::make('past_due_since')
                            ->label(__('payments.subscriptions.past_due_since'))
                            ->formatStateUsing($date)
                            ->visible(fn (Subscription $record): bool => $record->past_due_since !== null),
                        TextEntry::make('created_at')
                            ->label(__('payments.fields.created'))
                            ->formatStateUsing($date),
                        TextEntry::make('uuid')
                            ->label(__('payments.fields.reference'))
                            ->fontFamily('mono')
                            ->copyable(),
                        TextEntry::make('gateway_subscription_id')
                            ->label(__('payments.subscriptions.gateway_subscription_id'))
                            ->fontFamily('mono')
                            ->placeholder('—'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $settings = app(Settings::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'plan', 'price']))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('payments.fields.created'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDateTime($state))
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('payments.fields.customer'))
                    ->description(fn (Subscription $record): string => $record->user->email ?? '')
                    ->placeholder(__('payments.subscriptions.deleted_user'))
                    ->searchable(['name', 'email']),
                TextColumn::make('plan.name')
                    ->label(__('payments.subscriptions.plan'))
                    ->description(fn (Subscription $record): string => $record->price?->label() ?? ''),
                TextColumn::make('status')
                    ->label(__('payments.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => __($state->label()))
                    ->color(fn (SubscriptionStatus $state): string => $state->color())
                    ->description(fn (Subscription $record): ?string => $record->isCancelScheduled() ? static::endingLabel($record, $settings) : null),
                TextColumn::make('current_period_end')
                    ->label(__('payments.subscriptions.period_ends'))
                    ->formatStateUsing(fn ($state): string => $settings->formatDate($state))
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
                    ->options(collect(SubscriptionStatus::cases())->mapWithKeys(fn (SubscriptionStatus $status): array => [$status->value => __($status->label())])->all())
                    ->multiple(),
                SelectFilter::make('gateway')
                    ->label(__('payments.fields.gateway'))
                    ->options(collect(Gateway::cases())->filter(fn (Gateway $gateway): bool => $gateway->supportsSubscriptions())->mapWithKeys(fn (Gateway $gateway): array => [$gateway->value => __($gateway->label())])->all()),
                SelectFilter::make('mode')
                    ->label(__('payments.fields.mode'))
                    ->options(collect(GatewayMode::cases())->mapWithKeys(fn (GatewayMode $mode): array => [$mode->value => __($mode->label())])->all())
                    ->default(fn (): string => app(PaymentManager::class)->mode()->value),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    /**
     * "Ends 12 Oct 2026". Cast here because __() widens to string|array once
     * replacements are passed; the array arm is unreachable for this key.
     */
    protected static function endingLabel(Subscription $subscription, Settings $settings): string
    {
        return (string) __('payments.subscriptions.ending', ['date' => $settings->formatDate($subscription->ends_at)]);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'view' => ViewSubscription::route('/{record}'),
        ];
    }
}
