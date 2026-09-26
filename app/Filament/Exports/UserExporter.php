<?php

namespace App\Filament\Exports;

use App\Auth\Role;
use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * The customer list: who signed up, when, and what they subscribe to.
 */
class UserExporter extends SpreadsheetExporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label(__('exports.users.name'))
                ->preventFormulaInjection(),
            ExportColumn::make('email')
                ->label(__('exports.users.email'))
                ->preventFormulaInjection(),
            ExportColumn::make('role')
                ->label(__('exports.users.role'))
                ->formatStateUsing(fn (Role $state): string => __($state->label())),
            ExportColumn::make('email_verified_at')
                ->label(__('exports.users.verified'))
                ->state(fn (User $record): string => $record->email_verified_at !== null ? __('exports.yes') : __('exports.no')),
            ExportColumn::make('created_at')
                ->label(__('exports.users.registered'))
                ->state(fn (User $record): string => static::date($record->created_at)),
            // Read from the subscriptions modifyQuery() eager-loads, never
            // User::currentSubscription(): that queries per user, four
            // queries a row across an export of thousands.
            ExportColumn::make('subscription')
                ->label(__('exports.users.subscription'))
                ->state(function (User $record): string {
                    $subscription = $record->subscriptions->first();

                    return $subscription === null ? '' : __('exports.users.subscription_state', [
                        'plan' => $subscription->plan->name ?? '',
                        'status' => __($subscription->status->label()),
                    ]);
                }),
        ];
    }

    /**
     * Each user's started subscriptions, current first, with their plans:
     * two queries per chunk of users, whatever its size.
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['subscriptions' => fn ($subscriptions) => $subscriptions->currentFirst()->with('plan')]);
    }
}
