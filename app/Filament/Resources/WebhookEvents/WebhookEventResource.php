<?php

namespace App\Filament\Resources\WebhookEvents;

use App\Filament\Resources\WebhookEvents\Pages\ListWebhookEvents;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\WebhookEventStatus;
use App\Payments\PaymentManager;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Webhook deliveries received from the gateways, and what became of them.
 *
 * Read-only apart from Retry, for an event whose processing failed once the
 * cause is fixed. Events leave by retention (payments:prune-webhook-events).
 */
class WebhookEventResource extends Resource
{
    protected static ?string $model = WebhookEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('payments.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('payments.webhook_event.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.webhook_event.plural_label');
    }

    public static function canAccess(): bool
    {
        return app(PaymentManager::class)->enabled() && parent::canAccess();
    }

    /**
     * Failed events, so a stuck webhook is noticed without opening the list.
     */
    public static function getNavigationBadge(): ?string
    {
        $failed = WebhookEvent::query()->where('status', WebhookEventStatus::Failed->value)->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('event_id')
                    ->label(__('payments.fields.event_id'))
                    ->fontFamily('mono'),
                TextEntry::make('type')
                    ->label(__('payments.fields.event_type')),
                TextEntry::make('error')
                    ->label(__('payments.fields.error'))
                    ->visible(fn (WebhookEvent $record): bool => filled($record->error))
                    ->columnSpanFull(),
                // Escaped text, never rendered: the payload is gateway JSON that
                // carries customer-supplied names and addresses.
                TextEntry::make('payload')
                    ->label(__('payments.fields.payload'))
                    ->state(fn (WebhookEvent $record): string => (string) json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                    ->fontFamily('mono')
                    ->extraAttributes(['class' => 'whitespace-pre-wrap break-all'])
                    ->columnSpanFull(),
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
                TextColumn::make('gateway')
                    ->label(__('payments.fields.gateway'))
                    ->badge()
                    ->formatStateUsing(fn (Gateway $state): string => $state->label())
                    ->color(fn (Gateway $state): string => $state->color()),
                TextColumn::make('mode')
                    ->label(__('payments.fields.mode'))
                    ->badge()
                    ->formatStateUsing(fn (GatewayMode $state): string => $state->label())
                    ->color(fn (GatewayMode $state): string => $state->color()),
                TextColumn::make('type')
                    ->label(__('payments.fields.event_type'))
                    ->searchable(['type', 'event_id']),
                TextColumn::make('status')
                    ->label(__('payments.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (WebhookEventStatus $state): string => $state->label())
                    ->color(fn (WebhookEventStatus $state): string => $state->color()),
                TextColumn::make('attempts')
                    ->label(__('payments.fields.attempts')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('payments.fields.status'))
                    ->options(collect(WebhookEventStatus::cases())->mapWithKeys(fn (WebhookEventStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('gateway')
                    ->label(__('payments.fields.gateway'))
                    ->options([Gateway::Stripe->value => Gateway::Stripe->label(), Gateway::PayPal->value => Gateway::PayPal->label()]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')
                    ->label(__('payments.actions.retry'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (WebhookEvent $record): bool => $record->status === WebhookEventStatus::Failed)
                    ->authorize(fn (WebhookEvent $record): bool => auth()->user()?->can('retry', $record) ?? false)
                    ->requiresConfirmation()
                    ->action(function (WebhookEvent $record): void {
                        $record->forceFill(['status' => WebhookEventStatus::Received, 'error' => null])->save();
                        ProcessWebhookEvent::dispatch($record->id);

                        Notification::make()->success()->title(__('payments.actions.retried'))->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookEvents::route('/'),
        ];
    }
}
