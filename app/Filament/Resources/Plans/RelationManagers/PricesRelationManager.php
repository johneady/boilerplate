<?php

namespace App\Filament\Resources\Plans\RelationManagers;

use App\Filament\Forms\MoneyInput;
use App\Models\PlanPrice;
use App\Payments\Enums\BillingInterval;
use App\Payments\PaymentManager;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A plan's prices. Added, and switched off, but never edited or deleted: a
 * price change is a new price, so existing subscribers keep the one they
 * signed up for.
 */
class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('payments.plans.prices');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                MoneyInput::make('amount', app(PaymentManager::class)->currency())
                    ->label(__('payments.plans.amount'))
                    ->required()
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (is_numeric($value) && (float) $value <= 0) {
                            $fail(__('payments.links.amount_positive'));
                        }
                    }),
                Select::make('interval')
                    ->label(__('payments.plans.interval'))
                    ->options(BillingInterval::options())
                    ->default(BillingInterval::Month->value)
                    ->required()
                    ->selectablePlaceholder(false),
                TextInput::make('interval_count')
                    ->label(__('payments.plans.interval_count'))
                    ->helperText(__('payments.plans.interval_count_help'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(12)
                    ->default(1)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // Live subscriptions only: those still hold a user's slot.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['subscriptions' => fn (Builder $subscriptions): Builder => $subscriptions->whereNotNull('active_user_id')]))
            ->columns([
                TextColumn::make('amount')
                    ->label(__('payments.plans.price'))
                    ->state(fn (PlanPrice $record): string => $record->label()),
                TextColumn::make('subscriptions_count')
                    ->label(__('payments.plans.subscribers')),
                IconColumn::make('is_active')
                    ->label(__('payments.plans.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('id')
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'currency' => app(PaymentManager::class)->currency(),
                        'is_active' => true,
                    ]),
            ])
            ->recordActions([
                Action::make('deactivate')
                    ->label(__('payments.plans.deactivate_price'))
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('danger')
                    ->visible(fn (PlanPrice $record): bool => $record->is_active)
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->requiresConfirmation()
                    ->modalDescription(__('payments.plans.deactivate_price_help'))
                    ->action(fn (PlanPrice $record) => $record->update(['is_active' => false])),
                Action::make('activate')
                    ->label(__('payments.plans.activate_price'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->visible(fn (PlanPrice $record): bool => ! $record->is_active)
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->action(fn (PlanPrice $record) => $record->update(['is_active' => true])),
            ]);
    }

    /**
     * Prices are added on the plan's edit page, which is where this appears.
     */
    public function isReadOnly(): bool
    {
        return false;
    }
}
