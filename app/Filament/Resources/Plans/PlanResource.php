<?php

namespace App\Filament\Resources\Plans;

use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Filament\Resources\Plans\RelationManagers\PricesRelationManager;
use App\Models\Plan;
use App\Payments\Actions\SyncPlan;
use App\Payments\Exceptions\GatewayException;
use App\Payments\PaymentManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Subscription plans, defined here and synced out to the gateways.
 *
 * A plan is never deleted -- subscriptions name it -- and a price is never
 * edited: a new price is added and the old one switched off, which archives
 * it at the gateways while existing subscribers stay on it.
 */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 25;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('payments.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('payments.plan.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.plan.plural_label');
    }

    public static function canAccess(): bool
    {
        return app(PaymentManager::class)->enabled() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('payments.plans.name'))
                    ->required()
                    ->maxLength(100),
                TextInput::make('key')
                    ->label(__('payments.plans.key'))
                    ->helperText(__('payments.plans.key_help'))
                    ->required()
                    ->alphaDash()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    // Code checks access by this key, so it is fixed once
                    // subscribers may depend on it.
                    ->disabledOn('edit'),
                Textarea::make('description')
                    ->label(__('payments.plans.description'))
                    ->maxLength(1000)
                    ->rows(2)
                    ->columnSpanFull(),
                TagsInput::make('features')
                    ->label(__('payments.plans.features'))
                    ->helperText(__('payments.plans.features_help'))
                    ->default([])
                    ->columnSpanFull(),
                TextInput::make('trial_days')
                    ->label(__('payments.plans.trial_days'))
                    ->helperText(__('payments.plans.trial_days_help'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(365)
                    ->default(0)
                    ->required(),
                Toggle::make('taxable')
                    ->label(__('payments.plans.taxable'))
                    ->helperText(__('payments.plans.taxable_help'))
                    ->default(true),
                Toggle::make('is_active')
                    ->label(__('payments.plans.is_active'))
                    ->helperText(__('payments.plans.is_active_help'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('payments.plans.name'))
                    ->description(fn (Plan $record): string => $record->key)
                    ->searchable(),
                TextColumn::make('key')
                    ->label(__('payments.plans.key'))
                    ->searchable(isIndividual: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('prices')
                    ->label(__('payments.plans.prices'))
                    ->state(fn (Plan $record): string => $record->prices->where('is_active', true)->map->label()->implode(', ') ?: '—'),
                TextColumn::make('trial_days')
                    ->label(__('payments.plans.trial_days')),
                TextColumn::make('sync')
                    ->label(__('payments.plans.synced'))
                    ->state(fn (Plan $record): string => static::syncSummary($record)),
                IconColumn::make('is_active')
                    ->label(__('payments.plans.is_active'))
                    ->boolean(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('prices'))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                static::syncAction(),
            ])
            ->toolbarActions([]);
    }

    /**
     * Push the plan and its prices to every gateway now, reporting the
     * gateway's own words if it refuses.
     */
    public static function syncAction(): Action
    {
        return Action::make('sync')
            ->label(__('payments.plans.sync'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->authorize(fn (Plan $record): bool => auth()->user()?->can('sync', $record) ?? false)
            ->action(function (Plan $record): void {
                $sync = app(SyncPlan::class);
                $payments = app(PaymentManager::class);
                $failures = [];

                foreach ($payments->subscriptionGateways() as $gateway) {
                    try {
                        $sync->handle($record, $gateway, $payments->mode());
                    } catch (GatewayException $e) {
                        $failures[] = "{$gateway->label()}: {$e->getMessage()}";
                    }
                }

                $failures === []
                    ? Notification::make()->success()->title(__('payments.plans.synced_ok'))->send()
                    : Notification::make()->danger()->title(__('payments.plans.sync_failed'))->body(implode("\n", $failures))->send();
            });
    }

    /**
     * "Stripe: synced · PayPal: not synced" for the current mode.
     */
    public static function syncSummary(Plan $plan): string
    {
        $status = app(SyncPlan::class)->status($plan);

        if ($status === []) {
            return '—';
        }

        return collect($status)
            ->map(fn (bool $synced, string $gateway): string => (string) __($synced ? 'payments.plans.gateway_synced' : 'payments.plans.gateway_unsynced', ['gateway' => $gateway]))
            ->implode(' · ');
    }

    public static function getRelations(): array
    {
        return [
            PricesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
